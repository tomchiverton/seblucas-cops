<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL v2 or later (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 * @author     mikespub
 */

namespace SebLucas\Cops\Output;

use SebLucas\Cops\Calibre\Database;
use SebLucas\Cops\Calibre\Book;
use SebLucas\Cops\Calibre\Cover;
use SebLucas\Cops\Calibre\Filter;
use SebLucas\Cops\Input\Config;
use SebLucas\Cops\Input\Request;
use SebLucas\Cops\Input\Route;
use SebLucas\Cops\Model\Entry;
use SebLucas\Cops\Model\EntryBook;
use SebLucas\Cops\Pages\PageId;
use SebLucas\Cops\Pages\Page;
use Exception;

class JsonRenderer extends BaseRenderer
{
    /** @var Request */
    protected $request;
    /** @var ?int */
    protected $database = null;
    /** @var string */
    protected $handler;
    /** @var int|string */
    protected $page;
    /** @var array<string, mixed> */
    protected $extraParams = [];

    /**
     * Summary of getCurrentUrl
     * @param Request $request
     * @return string
     */
    public static function getCurrentUrl($request)
    {
        /**
        $pathInfo = $request->path();
        $queryString = $request->query();
        //return Route::link(static::$handler) . $pathInfo . Route::query($queryString, ['complete' => 1]);
        $uri = $pathInfo . Route::query($queryString, ['complete' => 1]);
        if (Config::get('front_controller')) {
            if (str_starts_with($uri, '/')) {
                return Route::base() . substr($uri, 1);
            }
            return Route::base() . $uri;
        }
         */
        $params = $request->urlParams;
        $params['complete'] = 1;
        return Route::link("json", null, $params);
    }

    /**
     * @param Book $book
     * @return array<string, mixed>
     */
    public function getBookContentArray($book)
    {
        $handler = $book->getHandler();
        $i = 0;
        $preferedData = [];
        foreach (Config::get('prefered_format') as $format) {
            if ($i == 2) {
                break;
            }
            $data = $book->getDataFormat($format);
            if ($data) {
                $i++;
                array_push($preferedData, [
                    "name" => $format,
                    "url" => $data->getHtmlLink(),
                    "viewUrl" => $data->getViewHtmlLink(),
                ]);
            }
        }

        $authors = [];
        foreach ($book->getAuthors() as $author) {
            $author->setHandler($handler);
            array_push($authors, [
                "name" => $author->name,
                "url" => $author->getUri(),
            ]);
        }

        $tags = [];
        foreach ($book->getTags() as $tag) {
            $tag->setHandler($handler);
            array_push($tags, [
                "name" => $tag->name,
                "url" => $tag->getUri(),
            ]);
        }

        $publisher = $book->getPublisher();
        if (empty($publisher)) {
            $pn = "";
            $pu = "";
        } else {
            $publisher->setHandler($handler);
            $pn = $publisher->name;
            $pu = $publisher->getUri();
        }

        $serie = $book->getSerie();
        if (empty($serie)) {
            $sn = "";
            $scn = "";
            $su = "";
        } else {
            $serie->setHandler($handler);
            $sn = $serie->name;
            $scn = str_format(localize("content.series.data"), $book->seriesIndex, $serie->name);
            $su = $serie->getUri();
        }
        $cc = $book->getCustomColumnValues(Config::get('calibre_custom_column_list'), true);

        return [
            "id" => $book->id,
            "detailurl" => $book->getDetailUrl($handler),
            "hasCover" => $book->hasCover,
            "preferedData" => $preferedData,
            "preferedCount" => count($preferedData),
            "rating" => $book->getRating(),
            "publisherName" => $pn,
            "publisherurl" => $pu,
            "pubDate" => $book->getPubDate(),
            "languagesName" => $book->getLanguages(),
            "authorsName" => $book->getAuthorsName(),
            "authors" => $authors,
            "tagsName" => $book->getTagsName(),
            "tags" => $tags,
            "seriesName" => $sn,
            "seriesIndex" => $book->seriesIndex,
            "seriesCompleteName" => $scn,
            "seriesurl" => $su,
            "customcolumns_list" => $cc,
        ];
    }

    /**
     * @param Book $book
     * @return array<string, mixed>
     */
    public function getFullBookContentArray($book)
    {
        $handler = $book->getHandler();
        $out = $this->getBookContentArray($book);
        $database = $book->getDatabaseId();

        $cover = new Cover($book);
        // set height for thumbnail here depending on opds vs. html (height x 2)
        if (in_array($handler, ['feed', 'opds'])) {
            $thumb = "opds2";
        } else {
            $thumb = "html2";
        }
        $out ["thumbnailurl"] = $cover->getThumbnailUri($thumb, false);
        $out ["coverurl"] = $cover->getCoverUri() ?? $out ["thumbnailurl"];
        $out ["content"] = $book->getComment(false);
        $out ["datas"] = [];
        $dataKindle = $book->GetMostInterestingDataToSendToKindle();
        foreach ($book->getDatas() as $data) {
            $tab = [
                "id" => $data->id,
                "format" => $data->format,
                "url" => $data->getHtmlLink(),
                "viewUrl" => $data->getViewHtmlLink(),
                "mail" => 0,
                "readerUrl" => "",
            ];
            if (!empty(Config::get('mail_configuration')) && !is_null($dataKindle) && $data->id == $dataKindle->id) {
                $tab ["mail"] = 1;
            }
            if ($data->format == "EPUB") {
                if (Config::get('use_route_urls')) {
                    $tab ["readerUrl"] = Route::link("read", null, ["data" => $data->id, "db" => ($database ?? 0), "title" => $book->getTitle()]);
                } else {
                    $tab ["readerUrl"] = Route::link("read", null, ["data" => $data->id, "db" => ($database ?? 0)]);
                }
            }
            array_push($out ["datas"], $tab);
        }
        $out ["extraFiles"] = [];
        foreach ($book->getExtraFiles() as $fileName) {
            $link = $book->getExtraFileLink($fileName);
            array_push($out ["extraFiles"], [
                "name" => $link->title,
                "url" => $link->hrefXhtml(),
                "length" => $link->length,
                "mtime" => $link->mtime,
            ]);
        }
        if (count($out ["extraFiles"]) > 0) {
            $url = Route::link("fetch", null, ["id" => $book->id, "db" => ($database ?? 0), "file" => "zipped"]);
            array_unshift($out ["extraFiles"], [
                "name" => " * ",
                "url" => $url,
            ]);
        }
        $out ["authors"] = [];
        foreach ($book->getAuthors() as $author) {
            $author->setHandler($handler);
            array_push($out ["authors"], [
                "name" => $author->name,
                "url" => $author->getUri(),
            ]);
        }
        $out ["tags"] = [];
        foreach ($book->getTags() as $tag) {
            $tag->setHandler($handler);
            array_push($out ["tags"], [
                "name" => $tag->name,
                "url" => $tag->getUri(),
            ]);
        }

        $out ["identifiers"] = [];
        foreach ($book->getIdentifiers() as $ident) {
            array_push($out ["identifiers"], [
                "name" => $ident->formattedType,
                "url" => $ident->getLink(),
            ]);
        }

        $out ["customcolumns_preview"] = $book->getCustomColumnValues(Config::get('calibre_custom_column_preview'), true);

        return $out;
    }

    /**
     * Summary of getContentArray
     * @param Entry|EntryBook|null $entry
     * @param array<string, mixed> $extraParams
     * @return array<string, mixed>|bool
     */
    public function getContentArray($entry, $extraParams = [])
    {
        if (is_null($entry)) {
            return false;
        }
        if ($entry instanceof EntryBook) {
            $out = [
                "title" => $entry->title,
                "book" => $this->getBookContentArray($entry->book),
                "thumbnailurl" => $entry->getThumbnail(),
                "coverurl" => $entry->getImage(),
            ];
            $out ["coverurl"] ??= $out ["thumbnailurl"];
            return $out;
        }
        $label = match ($entry->className) {
            'Author' => localize("authors.title"),
            'Identifier' => localize("identifiers.title"),
            'Language' => localize("languages.title"),
            'Publisher' => localize("publishers.title"),
            'Rating' => localize("ratings.title"),
            'Serie' => localize("series.title"),
            'Tag' => localize("tags.title"),
            default => $entry->className,
        };
        return [
            "class" => $label,
            "title" => $entry->title,
            "content" => $entry->content,
            "navlink" => $entry->getNavLink($extraParams),
            "number" => $entry->numberOfElement,
        ];
    }

    /**
     * Summary of getContentArrayTypeahead
     * @param Page $currentPage
     * @return array<mixed>
     */
    public function getContentArrayTypeahead($currentPage)
    {
        $out = [];
        foreach ($currentPage->entryArray as $entry) {
            if ($entry instanceof EntryBook) {
                array_push($out, [
                    "class" => $entry->className,
                    "title" => $entry->title,
                    "navlink" => $entry->book->getDetailUrl(),
                ]);
            } else {
                array_push($out, [
                    "class" => $entry->className,
                    "title" => $entry->title,
                    "navlink" => $entry->getNavLink(),
                ]);
            }
        }
        return $out;
    }

    /**
     * Summary of getCompleteArray
     * @return array<string, mixed>
     */
    public function getCompleteArray()
    {
        // check for it.c.config.ignored_categories.whatever in templates for category 'whatever'
        $ignoredCategories = ['dummy'];
        $ignoredCategories = array_merge($ignoredCategories, $this->request->option('ignored_categories'));
        $ignoredCategories = array_flip($ignoredCategories);

        $complete = [
            "version" => Config::VERSION,
            "i18n" => [
                "addedDateTitle" => localize("addeddate.title"),
                "coverAlt" => localize("i18n.coversection"),
                "authorsTitle" => localize("authors.title"),
                "authorTitle" => localize("author.title"),
                "allbooksTitle" => localize("allbooks.title"),
                "bookwordTitle" => localize("bookword.title"),
                "recentTitle" => localize("recent.title"),
                "tagsTitle" => localize("tags.title"),
                "tagwordTitle" => localize("tagword.title"),
                "linksTitle" => localize("links.title"),
                "seriesTitle" => localize("series.title"),
                "defaultTemplate" => localize("default.template"),
                "customizeTitle" => localize("customize.title"),
                "aboutTitle" => localize("about.title"),
                "firstAlt" => localize("paging.first.alternate"),
                "previousAlt" => localize("paging.previous.alternate"),
                "nextAlt" => localize("paging.next.alternate"),
                "lastAlt" => localize("paging.last.alternate"),
                "searchAlt" => localize("search.alternate"),
                "sortAlt" => localize("sort.alternate"),
                "sortByTitle" => localize("sortby.title"),
                "homeAlt" => localize("home.alternate"),
                "cogAlt" => localize("cog.alternate"),
                "permalinkAlt" => localize("permalink.alternate"),
                "publisherName" => localize("publisher.name"),
                "pubdateTitle" => localize("pubdate.title"),
                "languagesTitle" => localize("languages.title"),
                "languageTitle" => localize("language.title"),
                "contentTitle" => localize("content.summary"),
                "filterClearAll" => localize("filter.clearall"),
                "sortorderAsc" => localize("search.sortorder.asc"),
                "sortorderDesc" => localize("search.sortorder.desc"),
                "customizeEmail" => localize("customize.email"),
                "ratingsTitle" => localize("ratings.title"),
                "ratingTitle" => localize("rating.title"),
                "librariesTitle" => localize("libraries.title"),
                "libraryTitle" => localize("library.title"),
                "linkTitle" => localize("extra.link"),
                "filesTitle" => localize("extra.files"),
                "titleTitle" => localize("title.title"),
                "filtersTitle" => localize("filters.title"),
                "downloadAllTitle" => localize("downloadall.title"),
                "downloadAllTooltip" => localize("downloadall.tooltip"),
            ],
            "url" => [
                "detailUrl" => str_replace(['%7B', '%7D'], ['{', '}'], Route::link($this->handler, PageId::BOOK_DETAIL, ['id' => '{0}', 'db' => '{1}'])),
                // route urls do not accept non-numeric id or db to find match here
                "coverUrl" => str_replace(['0', '1'], ['{0}', '{1}'], Route::link("fetch", null, ['id' => '0', 'db' => '1'])),
                "thumbnailUrl" => str_replace(['0', '1'], ['{0}', '{1}'], Route::link("fetch", null, ['thumb' => 'html', 'id' => '0', 'db' => '1'])),
            ],
            "config" => [
                "use_fancyapps" => Config::get('use_fancyapps'),
                "max_item_per_page" => Config::get('max_item_per_page'),
                "kindleHack"        => "",
                "server_side_rendering" => $this->request->render(),
                "html_tag_filter" => Config::get('html_tag_filter'),
                "ignored_categories" => $ignoredCategories,
            ],
        ];
        if (Config::get('thumbnail_handling') == "1") {
            $complete["url"]["thumbnailUrl"] = $complete["url"]["coverUrl"];
        } elseif (!empty(Config::get('thumbnail_handling'))) {
            $complete["url"]["thumbnailUrl"] = Config::get('thumbnail_handling');
        }
        if (preg_match("/./", $this->request->agent())) {
            $complete["config"]["kindleHack"] = 'style="text-decoration: none !important;"';
        }
        return $complete;
    }

    /**
     * Summary of addPagination
     * @param Page $currentPage
     * @return array<string, mixed>
     */
    public function addPagination($currentPage)
    {
        $out = [];
        if (!$currentPage->isPaginated()) {
            $out ["isPaginated"] = 0;
            return $out;
        }
        $prevLink = $currentPage->getPrevLink();
        $nextLink = $currentPage->getNextLink();
        $out ["isPaginated"] = 1;
        $out ["firstLink"] = "";
        $out ["prevLink"] = "";
        if (!is_null($prevLink)) {
            $out ["firstLink"] = $currentPage->getFirstLink()->hrefXhtml();
            $out ["prevLink"] = $prevLink->hrefXhtml();
        }
        $out ["nextLink"] = "";
        $out ["lastLink"] = "";
        if (!is_null($nextLink)) {
            $out ["nextLink"] = $nextLink->hrefXhtml();
            $out ["lastLink"] = $currentPage->getLastLink()->hrefXhtml();
        }
        $out ["maxPage"] = $currentPage->getMaxPage();
        $out ["currentPage"] = $currentPage->n;
        return $out;
    }

    /**
     * Summary of addSortFilter
     * @param Page $currentPage
     * @return array<string, mixed>
     */
    public function addSortFilter($currentPage)
    {
        $out = [];
        $out ["sorted"] = $currentPage->sorted ?? '';
        $out ["sortedBy"] = explode(' ', $out ["sorted"])[0];
        $out ["sortedDir"] = '';
        if (!empty($out ["sortedBy"])) {
            if (in_array($out ["sortedBy"], ['title', 'author', 'sort', 'name', 'type', 'lang_code', 'letter', 'year', 'range', 'value', 'groupid', 'series_index'])) {
                // default ascending order for anything vaguely alphabetical or grouped
                $out ["sortedDir"] = str_contains($out ["sorted"], 'desc') ? 'desc' : 'asc';
            } elseif (in_array($out ["sortedBy"], ['pubdate', 'rating', 'timestamp', 'count', 'series'])) {
                // default descending order for anything vaguely numerical or recent
                $out ["sortedDir"] = str_contains($out ["sorted"], 'asc') ? 'asc' : 'desc';
            } else {
                // default descending order for anything else we forgot above :-)
                $out ["sortedDir"] = str_contains($out ["sorted"], 'asc') ? 'asc' : 'desc';
            }
        }
        $out ["containsBook"] = 0;
        $out ["filterurl"] = false;
        if ($currentPage->containsBook()) {
            $out ["containsBook"] = 1;
            // support {{=str_format(it.sorturl, "pubdate")}} etc. in templates (use double quotes for sort field)
            $params = $this->request->getCleanParams();
            $params['sort'] = '{0}';
            $out ["sorturl"] = str_replace('%7B0%7D', '{0}', Route::link($this->handler, null, $params));
            $out ["sortoptions"] = $currentPage->getSortOptions();
            if ($currentPage->canFilter()) {
                $params = $this->request->getCleanParams();
                $params['filter'] = 1;
                $out ["filterurl"] = Route::link($this->handler, null, $params);
            }
        } elseif (!empty($currentPage->extra)) {
            // show extra info or series in Page*Detail (without books)
            $out ["containsBook"] = 1;
            $out ["sortoptions"] = [];
            if ($currentPage->canFilter()) {
                $params = $this->request->getCleanParams();
                $params['filter'] = 1;
                $out ["filterurl"] = Route::link($this->handler, null, $params);
            }
        } else {
            if ($currentPage->isPaginated()) {
                // support {{=str_format(it.sorturl, "count")}} etc. in templates (use double quotes for sort field)
                $params = $this->request->getCleanParams();
                $params['sort'] = '{0}';
                $out ["sorturl"] = str_replace('%7B0%7D', '{0}', Route::link($this->handler, null, $params));
                $out ["sortoptions"] = [
                    'name' => localize("sort.names"),
                    'count' => localize("sort.count"),
                ];
            }
            if ($currentPage->canFilter()) {
                $params = $this->request->getCleanParams();
                $params['filter'] = null;
                $out ["filterurl"] = Route::link($this->handler, null, $params);
            }
        }
        return $out;
    }

    /**
     * Summary of getFiltersArray
     * @return array<mixed>|false
     */
    public function getFiltersArray()
    {
        $filters = false;
        if (!$this->request->hasFilter()) {
            return $filters;
        }
        $filters = [];
        foreach (Filter::getEntryArray($this->request, $this->database) as $entry) {
            array_push($filters, $this->getContentArray($entry, ['filter' => 1]));
        }
        if (empty($filters)) {
            $filters = false;
        }
        return $filters;
    }

    /**
     * Summary of getHomeUrl
     * @param string $baseurl
     * @return string
     */
    public function getHomeUrl($baseurl)
    {
        $homepage = PageId::getHomePage();
        // multiple database setup
        if ($this->page != PageId::INDEX && !is_null($this->database)) {
            if ($homepage != PageId::INDEX) {
                $homeurl = Route::link($this->handler, PageId::INDEX, ['db' => $this->database]);
            } else {
                $homeurl = Route::link($this->handler, null, ['db' => $this->database]);
            }
        } elseif ($homepage != PageId::INDEX) {
            $homeurl = Route::link($this->handler, PageId::INDEX);
        } else {
            $homeurl = $baseurl;
        }
        return $homeurl;
    }

    /**
     * Summary of getParentLink
     * @param Page $currentPage
     * @param array<mixed>|false $filters
     * @param string $homeurl
     * @return string
     */
    public function getParentUrl($currentPage, $filters, $homeurl)
    {
        $parenturl = "";
        if (!empty($filters) && !empty($currentPage->currentUri)) {
            // if filtered, use the unfiltered uri as parent first
            $parenturl = $currentPage->currentUri;
        } elseif (!empty($currentPage->parentUri)) {
            // otherwise use the parent uri
            $parenturl = $currentPage->parentUri;
        } elseif ($this->page != PageId::INDEX) {
            if ($this->request->hasFilter()) {
                $filterParams = $this->request->getFilterParams();
                $filterParams["db"] = $this->database;
                $parenturl = Route::link($this->handler, PageId::INDEX, $filterParams);
            } else {
                $parenturl = $homeurl;
            }
        }
        return $parenturl;
    }

    /**
     * Summary of getHierarchy
     * @param Page $currentPage
     * @param array<string, mixed> $extraParams
     * @return array<mixed>|false
     */
    public function getHierarchy($currentPage, $extraParams)
    {
        $hierarchy = false;
        if (!$currentPage->hierarchy) {
            return $hierarchy;
        }
        $hierarchy = [
            "parent" => $this->getContentArray($currentPage->hierarchy['parent'], $extraParams),
            "current" => $this->getContentArray($currentPage->hierarchy['current'], $extraParams),
            "children" => [],
            "hastree" => $this->request->get('tree', false),
        ];
        foreach ($currentPage->hierarchy['children'] as $entry) {
            array_push($hierarchy["children"], $this->getContentArray($entry, $extraParams));
        }
        return $hierarchy;
    }

    /**
     * Summary of getSeries
     * @param Page $currentPage
     * @param array<string, mixed> $extraParams
     * @return array<mixed>|false
     */
    public function getSeries($currentPage, $extraParams)
    {
        $series = false;
        if (empty($currentPage->extra['series'])) {
            return $series;
        }
        $series = [];
        foreach ($currentPage->extra['series'] as $entry) {
            array_push($series, $this->getContentArray($entry, $extraParams));
        }
        return $series;
    }

    /**
     * Summary of getDownloadLinks
     * @param Page $currentPage
     * @param ?int $qid
     * @return array<mixed>|false
     */
    public function getDownloadLinks($currentPage, $qid)
    {
        // avoid messy Javascript issue with empty array being truthy or falsy - see #40
        $download = false;
        if (!$currentPage->containsBook()) {
            return $download;
        }
        // download per page
        if (!empty(Config::get('download_page'))) {
            $download = [];
            foreach (Config::get('download_page') as $format) {
                $params = $this->request->getCleanParams();
                $params['type'] = strtolower((string) $format);
                $url = Route::link(Zipper::$handler, null, $params);
                array_push($download, ['url' => $url, 'format' => $format]);
            }
            return $download;
        }
        if (empty($qid)) {
            return $download;
        }
        // download per series
        if ($this->page == PageId::SERIE_DETAIL && !empty(Config::get('download_series'))) {
            $download = [];
            foreach (Config::get('download_series') as $format) {
                $params = [];
                $params['series'] = $qid;
                $params['type'] = strtolower((string) $format);
                $params['db'] = $this->database;
                $url = Route::link(Zipper::$handler, null, $params);
                array_push($download, ['url' => $url, 'format' => $format]);
            }
            return $download;
        }
        // download per author
        if ($this->page == PageId::AUTHOR_DETAIL && !empty(Config::get('download_author'))) {
            $download = [];
            foreach (Config::get('download_author') as $format) {
                $params = [];
                $params['author'] = $qid;
                $params['type'] = strtolower((string) $format);
                $params['db'] = $this->database;
                $url = Route::link(Zipper::$handler, null, $params);
                array_push($download, ['url' => $url, 'format' => $format]);
            }
            return $download;
        }
        return $download;
    }

    /**
     * Summary of getJson
     * @param Request $request
     * @param bool $complete
     * @return array<string, mixed>
     */
    public function getJson($request, $complete = false)
    {
        // Use the configured home page if needed
        $homepage = PageId::getHomePage();
        $page = $request->get("page", $homepage);
        $search = $request->get("search");
        $qid = $request->getId();
        $database = $request->database();
        $libraryId = $request->getVirtualLibrary();

        try {
            $currentPage = PageId::getPage($page, $request);
        } catch (Exception $e) {
            // this will call exit()
            Response::sendError($request, $e->getMessage());
        }

        // adapt handler based on $request e.g. for rest api
        $handler = $request->getHandler();

        if ($search) {
            return $this->getContentArrayTypeahead($currentPage);
        }

        $this->setRequest($request);

        $out = [ "title" => $currentPage->title];
        $out ["parentTitle"] = $currentPage->parentTitle;
        if (!empty($out ["parentTitle"])) {
            $out ["title"] = $out ["parentTitle"] . " > " . $out ["title"];
        }
        $out ["baseurl"] = Route::link($handler);
        $entries = [];
        $extraParams = [];
        $out ["isFilterPage"] = false;
        if (!empty($request->get('filter')) && !empty($currentPage->filterParams)) {
            // @todo get rid of extraParams as filters should be included in navlink now
            $extraParams = $currentPage->filterParams;
            $out ["isFilterPage"] = true;
        }
        foreach ($currentPage->entryArray as $entry) {
            array_push($entries, $this->getContentArray($entry, $extraParams));
        }
        if (!is_null($currentPage->book)) {
            // setting this on Book gets cascaded down to Data if isEpubValidOnKobo()
            if (Config::get('provide_kepub') == "1" && preg_match("/Kobo/", $request->agent())) {
                $currentPage->book->updateForKepub = true;
            }
            $out ["book"] = $this->getFullBookContentArray($currentPage->book);
        } elseif ($page == PageId::BOOK_DETAIL) {
            $page = PageId::INDEX;
        }
        $this->page = $page;

        $out ["databaseId"] = $database ?? "";
        $out ["databaseName"] = Database::getDbName($database);
        if ($out ["databaseId"] == "") {
            $out ["databaseName"] = "";
        }
        $out ["libraryId"] = $libraryId ?? "";
        $out ["libraryName"] = Config::get('title_default');
        $out ["fullTitle"] = $out ["title"];
        $out ["multipleDatabase"] = Database::isMultipleDatabaseEnabled() ? 1 : 0;
        if (!empty($out ["multipleDatabase"]) && $out ["databaseId"] != "" && $out ["databaseName"] != $out ["fullTitle"]) {
            $out ["fullTitle"] = $out ["databaseName"] . " > " . $out ["fullTitle"];
        }
        $out ["page"] = $page;
        $out ["entries"] = $entries;
        $out ["entriesCount"] = count($entries);
        $out = array_replace($out, $this->addPagination($currentPage));
        if (!is_null($request->get("complete")) || $complete) {
            $out ["c"] = $this->getCompleteArray();
        }

        $out = array_replace($out, $this->addSortFilter($currentPage));
        $out["filters"] = $this->getFiltersArray();

        $out["abouturl"] = Route::link($handler, PageId::ABOUT, ['db' => $database]);
        $out["customizeurl"] = Route::link($handler, PageId::CUSTOMIZE, ['db' => $database]);

        if ($page == PageId::ABOUT) {
            $out ["fullhtml"] = $currentPage->getContent();
        }

        $out ["homeurl"] = $this->getHomeUrl($out["baseurl"]);
        $out ["parenturl"] = $this->getParentUrl($currentPage, $out["filters"], $out["homeurl"]);
        $out ["hierarchy"] = $this->getHierarchy($currentPage, $extraParams);
        $out ["extra"] = $currentPage->extra;
        if (!empty($currentPage->extra['series'])) {
            $out ["extra"]["series"] = $this->getSeries($currentPage, $extraParams);
        }
        $out ["assets"] = Route::path(Config::get('assets'));
        $out ["download"] = $this->getDownloadLinks($currentPage, $qid);

        /** @phpstan-ignore-next-line */
        if (Database::KEEP_STATS) {
            $out ["dbstats"] = Database::getDbStatistics();
        }

        return $out;
    }

    /**
     * Summary of setRequest
     * @param Request $request
     * @return void
     */
    public function setRequest($request)
    {
        $this->request = $request;
        $this->database = $request->database();
        $this->handler = $request->getHandler();
        // Use the configured home page if needed
        $homepage = PageId::getHomePage();
        $this->page = $request->get("page", $homepage);
    }
}
