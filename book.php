<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');
require_once('serie.php');
require_once('author.php');
require_once('tag.php');
require_once('language.php');
require_once("customcolumn.php");
require_once('data.php');
require_once('resources/php-epub-meta/epub.php');

// Silly thing because PHP forbid string concatenation in class const
define ('SQL_BOOKS_LEFT_JOIN', "left outer join comments on comments.book = books.id 
                                left outer join books_ratings_link on books_ratings_link.book = books.id 
                                left outer join ratings on books_ratings_link.rating = ratings.id ");
define ('SQL_BOOKS_BY_FIRST_LETTER', "select {0} from books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where upper (books.sort) like ? order by books.sort");
define ('SQL_BOOKS_BY_AUTHOR', "select {0} from books_authors_link, books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where books_authors_link.book = books.id and author = ? {1} order by pubdate");
define ('SQL_BOOKS_BY_SERIE', "select {0} from books_series_link, books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where books_series_link.book = books.id and series = ? {1} order by series_index");
define ('SQL_BOOKS_BY_TAG', "select {0} from books_tags_link, books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where books_tags_link.book = books.id and tag = ? {1} order by sort");
define ('SQL_BOOKS_BY_LANGUAGE', "select {0} from books_languages_link, books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where books_languages_link.book = books.id and lang_code = ? {1} order by sort");
define ('SQL_BOOKS_BY_CUSTOM', "select {0} from {2}, books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where {2}.book = books.id and {2}.{3} = ? {1} order by sort");
define ('SQL_BOOKS_QUERY', "select {0} from books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where (exists (select null from authors, books_authors_link where book = books.id and author = authors.id and authors.name like ?) or title like ?) {1} order by books.sort");
define ('SQL_BOOKS_RECENT', "select {0} from books " . SQL_BOOKS_LEFT_JOIN . "
                                                    where 1=1 {1} order by timestamp desc limit ");

class Book extends Base {
    const ALL_BOOKS_UUID = "urn:uuid";
    const ALL_BOOKS_ID = "calibre:books";
    const ALL_RECENT_BOOKS_ID = "calibre:recentbooks";
    const BOOK_COLUMNS = "books.id as id, books.title as title, text as comment, path, timestamp, pubdate, series_index, uuid, has_cover, ratings.rating";
    
    const SQL_BOOKS_LEFT_JOIN = SQL_BOOKS_LEFT_JOIN;
    const SQL_BOOKS_BY_FIRST_LETTER = SQL_BOOKS_BY_FIRST_LETTER;
    const SQL_BOOKS_BY_AUTHOR = SQL_BOOKS_BY_AUTHOR;
    const SQL_BOOKS_BY_SERIE = SQL_BOOKS_BY_SERIE;
    const SQL_BOOKS_BY_TAG = SQL_BOOKS_BY_TAG;
    const SQL_BOOKS_BY_LANGUAGE = SQL_BOOKS_BY_LANGUAGE;
    const SQL_BOOKS_BY_CUSTOM = SQL_BOOKS_BY_CUSTOM;
    const SQL_BOOKS_QUERY = SQL_BOOKS_QUERY;
    const SQL_BOOKS_RECENT = SQL_BOOKS_RECENT;
    
    public $id;
    public $title;
    public $timestamp;
    public $pubdate;
    public $path;
    public $uuid;
    public $hasCover;
    public $relativePath;
    public $seriesIndex;
    public $comment;
    public $rating;
    public $datas = NULL;
    public $authors = NULL;
    public $serie = NULL;
    public $tags = NULL;
    public $languages = NULL;
    public $format = array ();

    
    public function __construct($line) {
        global $config;
        $this->id = $line->id;
        $this->title = $line->title;
        $this->timestamp = strtotime ($line->timestamp);
        $this->pubdate = strtotime ($line->pubdate);
        $this->path = Base::getDbDirectory () . $line->path;
        $this->relativePath = $line->path;
        $this->seriesIndex = $line->series_index;
        $this->comment = $line->comment;
        $this->uuid = $line->uuid;
        $this->hasCover = $line->has_cover;
        if (!file_exists ($this->getFilePath ("jpg"))) {
            // double check
            $this->hasCover = 0;
        }
        $this->rating = $line->rating;
    }
        
    public function getEntryId () {
        return self::ALL_BOOKS_UUID.":".$this->uuid;
    }
    
    public static function getEntryIdByLetter ($startingLetter) {
        return self::ALL_BOOKS_ID.":letter:".$startingLetter;
    }
    
    public function getUri () {
        return "?page=".parent::PAGE_BOOK_DETAIL."&id=$this->id";
    }
    
    public function getContentArray () {
        global $config;
        $i = 0;
        $preferedData = array ();
        foreach ($config['cops_prefered_format'] as $format)
        {
            if ($i == 2) { break; }
            if ($data = $this->getDataFormat ($format)) {
                $i++;
                array_push ($preferedData, array ("url" => $data->getHtmlLink (), "name" => $format));
            }
        }
        $serie = $this->getSerie ();
        if (is_null ($serie)) {
            $sn = "";
            $scn = "";
            $su = "";
        } else {
            $sn = $serie->name;
            $scn = str_format (localize ("content.series.data"), $this->seriesIndex, $serie->name);
            $link = new LinkNavigation ($serie->getUri ());
            $su = $link->hrefXhtml ();
        }
        
        return array ("hasCover" => $this->hasCover,
                      "preferedData" => $preferedData,
                      "detailUrl" => $this->getDetailUrl (),
                      "rating" => $this->getRating (),
                      "pubDate" => $this->getPubDate (),
                      "languagesName" => $this->getLanguages (),
                      "authorsName" => $this->getAuthorsName (),
                      "tagsName" => $this->getTagsName (),
                      "seriesName" => $sn,
                      "seriesCompleteName" => $scn,
                      "seriesurl" => $su);  
    
    }
    public function getFullContentArray () {
        $out = $this->getContentArray ();
        
        $out ["detailurl"] = $this->getDetailUrl (true);
        $out ["coverurl"] = Data::getLink ($this, "jpg", "image/jpeg", Link::OPDS_IMAGE_TYPE, "cover.jpg", NULL)->hrefXhtml ();
        $out ["thumbnailurl"] = Data::getLink ($this, "jpg", "image/jpeg", Link::OPDS_THUMBNAIL_TYPE, "cover.jpg", NULL, NULL, 150)->hrefXhtml ();
        $out ["content"] = $this->getComment (false);
        $out ["datas"] = array ();
        foreach ($this->getDatas() as $data) {
            array_push ($out ["datas"], array ("format" => $data->format, "url" => $data->getHtmlLink ()));
        }
        $out ["authors"] = array ();
        foreach ($this->getAuthors () as $author) {
            $link = new LinkNavigation ($author->getUri ());
            array_push ($out ["authors"], array ("name" => $author->name, "url" => $link->hrefXhtml ()));
        }
        $out ["tags"] = array ();
        foreach ($this->getTags () as $tag) {
            $link = new LinkNavigation ($tag->getUri ());
            array_push ($out ["tags"], array ("name" => $tag->name, "url" => $link->hrefXhtml ()));
        }
        ;
        return $out;
    }
    
    public function getDetailUrl ($permalink = false) {
        global $config;
        $urlParam = $this->getUri ();
        if (!is_null (GetUrlParam (DB))) $urlParam = addURLParameter ($urlParam, DB, GetUrlParam (DB));
        $urlParam = str_replace ("&", "&amp;", $urlParam);
        if ($permalink || getCurrentOption ('use_fancyapps') == 0) { 
            return 'index.php' . $urlParam; 
        } else { 
            return 'bookdetail.php' . $urlParam;
        }
    }
    
    public function getTitle () {
        return $this->title;
    }
    
    public function getAuthors () {
        if (is_null ($this->authors)) {
            $this->authors = Author::getAuthorByBookId ($this->id);
        }
        return $this->authors;
    }
    
    public static function getFilterString () {
        $filter = getURLParam ("tag", NULL);
        if (empty ($filter)) return "";
        
        $exists = true;
        if (preg_match ("/^!(.*)$/", $filter, $matches)) {
            $exists = false;
            $filter = $matches[1];    
        }
        
        $result = "exists (select null from books_tags_link, tags where books_tags_link.book = books.id and books_tags_link.tag = tags.id and tags.name = '" . $filter . "')";
        
        if (!$exists) {
            $result = "not " . $result;
        }
    
        return "and " . $result;
    }
    
    public function getAuthorsName () {
        return implode (", ", array_map (function ($author) { return $author->name; }, $this->getAuthors ()));
    }
    
    public function getSerie () {
        if (is_null ($this->serie)) {
            $this->serie = Serie::getSerieByBookId ($this->id);
        }
        return $this->serie;
    }
    
    public function getLanguages () {
        $lang = array ();
        $result = parent::getDb ()->prepare('select languages.lang_code
                from books_languages_link, languages
                where books_languages_link.lang_code = languages.id
                and book = ?
                order by item_order');
        $result->execute (array ($this->id));
        while ($post = $result->fetchObject ())
        {
            array_push ($lang, localize("languages.".$post->lang_code));
        }
        return implode (", ", $lang);
    }
    
    public function getTags () {
        if (is_null ($this->tags)) {
            $this->tags = array ();
            
            $result = parent::getDb ()->prepare('select tags.id as id, name
                from books_tags_link, tags
                where tag = tags.id
                and book = ?
                order by name');
            $result->execute (array ($this->id));
            while ($post = $result->fetchObject ())
            {
                array_push ($this->tags, new Tag ($post->id, $post->name));
            }
        }
        return $this->tags;
    }
    
    public function getDatas ()
    {
        if (is_null ($this->datas)) {
            $this->datas = array ();
        
            $result = parent::getDb ()->prepare('select id, format, name
    from data where book = ?');
            $result->execute (array ($this->id));
            
            while ($post = $result->fetchObject ())
            {
                array_push ($this->datas, new Data ($post, $this));
            }
        }
        return $this->datas;
    }
	
	public function GetMostInterestingDataToSendToKindle ()
	{
		$bestFormatForKindle = array ("PDF", "MOBI");
		$bestRank = -1;
		$bestData = NULL;
		foreach ($this->getDatas () as $data) {
			$key = array_search ($data->format, $bestFormatForKindle);
			if ($key !== false && $key > $bestRank) {
				$bestRank = $key;
				$bestData = $data;
			}
		}
		return $bestData;
	}
    
    public function getDataById ($idData)
    {
        foreach ($this->getDatas () as $data) {
            if ($data->id == $idData) {
                return $data;
            }
        }
        return NULL;
    }

    
    public function getTagsName () {
        return implode (", ", array_map (function ($tag) { return $tag->name; }, $this->getTags ()));
    }
    
    public function getRating () {
        if (is_null ($this->rating) || $this->rating == 0) {
            return "";
        }
        $retour = "";
        for ($i = 0; $i < $this->rating / 2; $i++) {
            $retour .= "&#9733;";
        }
        for ($i = 0; $i < 5 - $this->rating / 2; $i++) {
            $retour .= "&#9734;";
        }
        return $retour;
    }
     
    public function getPubDate () {
        if (is_null ($this->pubdate) || ($this->pubdate <= -58979923200)) {
            return "";
        }
        else {
            return date ("Y", $this->pubdate);
        }
    }
    
    public function getComment ($withSerie = true) {
        $addition = "";
        $se = $this->getSerie ();
        if (!is_null ($se) && $withSerie) {
            $addition = $addition . "<strong>" . localize("content.series") . "</strong>" . str_format (localize ("content.series.data"), $this->seriesIndex, htmlspecialchars ($se->name)) . "<br />\n";
        }
        if (preg_match ("/<\/(div|p|a|span)>/", $this->comment))
        {
            return $addition . html2xhtml ($this->comment);
        }
        else
        {
            return $addition . htmlspecialchars ($this->comment);
        }
    }
    
    public function getDataFormat ($format) {
        foreach ($this->getDatas () as $data)
        {
            if ($data->format == $format)
            {
                return $data;
            }
        }
        return NULL;
    }
    
    public function getFilePath ($extension, $idData = NULL, $relative = false)
    {
        $file = NULL;
        if ($extension == "jpg")
        {
            $file = "cover.jpg";
        }
        else
        {
            $data = $this->getDataById ($idData);
            $file = $data->name . "." . strtolower ($data->format);
        }

        if ($relative)
        {
            return $this->relativePath."/".$file;
        }
        else
        {
            return $this->path."/".$file;
        }
    }
    
    public function getUpdatedEpub ($idData)
    {
        global $config;
        $data = $this->getDataById ($idData);
            
        try
        {
            $epub = new EPub ($data->getLocalPath ());
            
            $epub->Title ($this->title);
            $authorArray = array ();
            foreach ($this->getAuthors() as $author) {
                $authorArray [$author->sort] = $author->name;
            }
            $epub->Authors ($authorArray);
            $epub->Language ($this->getLanguages ());
            $epub->Description ($this->getComment (false));
            $epub->Subjects ($this->getTagsName ());
            $epub->Cover2 ($this->getFilePath ("jpg"), "image/jpeg");
            $epub->Calibre ($this->uuid);
            $se = $this->getSerie ();
            if (!is_null ($se)) {
                $epub->Serie ($se->name);
                $epub->SerieIndex ($this->seriesIndex);
            }
            if ($config['cops_provide_kepub'] == "1"  && preg_match("/Kobo/", $_SERVER['HTTP_USER_AGENT'])) {
                $epub->updateForKepub ();
            }
            $epub->download ($data->getUpdatedFilenameEpub ());
        }
        catch (Exception $e)
        {
            echo "Exception : " . $e->getMessage();
        }
    }
    
    public function getLinkArray ()
    {
        global $config;
        $linkArray = array();
        
        if ($this->hasCover)
        {
            array_push ($linkArray, Data::getLink ($this, "jpg", "image/jpeg", Link::OPDS_IMAGE_TYPE, "cover.jpg", NULL));
            
            array_push ($linkArray, Data::getLink ($this, "jpg", "image/jpeg", Link::OPDS_THUMBNAIL_TYPE, "cover.jpg", NULL));
        }
        
        foreach ($this->getDatas () as $data)
        {
            if ($data->isKnownType ())
            {
                array_push ($linkArray, $data->getDataLink (Link::OPDS_ACQUISITION_TYPE, "Download"));
            }
        }
                
        foreach ($this->getAuthors () as $author) {
            array_push ($linkArray, new LinkNavigation ($author->getUri (), "related", str_format (localize ("bookentry.author"), localize ("splitByLetter.book.other"), $author->name)));
        }
        
        $serie = $this->getSerie ();
        if (!is_null ($serie)) {
            array_push ($linkArray, new LinkNavigation ($serie->getUri (), "related", str_format (localize ("content.series.data"), $this->seriesIndex, $serie->name)));
        }
        
        return $linkArray;
    }

    
    public function getEntry () {    
        return new EntryBook ($this->getTitle (), $this->getEntryId (), 
            $this->getComment (), "text/html", 
            $this->getLinkArray (), $this);
    }
    
    public static function getBookCount($database = NULL) {
        global $config;
        $nBooks = parent::getDb ($database)->query('select count(*) from books')->fetchColumn();
        return $nBooks;
    }

    public static function getCount() {
        global $config;
        $nBooks = parent::getDb ()->query('select count(*) from books')->fetchColumn();
        $result = array();
        $entry = new Entry (localize ("allbooks.title"), 
                          self::ALL_BOOKS_ID, 
                          str_format (localize ("allbooks.alphabetical", $nBooks), $nBooks), "text", 
                          array ( new LinkNavigation ("?page=".parent::PAGE_ALL_BOOKS)));
        array_push ($result, $entry);
        $entry = new Entry (localize ("recent.title"), 
                          self::ALL_RECENT_BOOKS_ID, 
                          str_format (localize ("recent.list"), $config['cops_recentbooks_limit']), "text", 
                          array ( new LinkNavigation ("?page=".parent::PAGE_ALL_RECENT_BOOKS)));
        array_push ($result, $entry);
        return $result;
    }
        
    public static function getBooksByAuthor($authorId, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_AUTHOR, array ($authorId), $n);
    }

    
    public static function getBooksBySeries($serieId, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_SERIE, array ($serieId), $n);
    }
    
    public static function getBooksByTag($tagId, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_TAG, array ($tagId), $n);
    }
    
    public static function getBooksByLanguage($languageId, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_LANGUAGE, array ($languageId), $n);
    }

    public static function getBooksByCustom($customId, $id, $n) {
        $query = str_format (self::SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", CustomColumn::getTableLinkName ($customId), CustomColumn::getTableLinkColumn ($customId));
        return self::getEntryArray ($query, array ($id), $n);
    }
    
    public static function getBookById($bookId) {
        $result = parent::getDb ()->prepare('select ' . self::BOOK_COLUMNS . '
from books ' . self::SQL_BOOKS_LEFT_JOIN . '
where books.id = ?');
        $result->execute (array ($bookId));
        while ($post = $result->fetchObject ())
        {
            $book = new Book ($post);
            return $book;
        }
        return NULL;
    }
    
    public static function getBookByDataId($dataId) {
        $result = parent::getDb ()->prepare('select ' . self::BOOK_COLUMNS . ', data.name, data.format
from data, books ' . self::SQL_BOOKS_LEFT_JOIN . '
where data.book = books.id and data.id = ?');
        $result->execute (array ($dataId));
        while ($post = $result->fetchObject ())
        {
            $book = new Book ($post);
            $data = new Data ($post, $book);
            $data->id = $dataId;
            $book->datas = array ($data);
            return $book;
        }
        return NULL;
    }
    
    public static function getBooksByQuery($query, $n, $database = NULL) {
        return self::getEntryArray (self::SQL_BOOKS_QUERY, array ("%" . $query . "%", "%" . $query . "%"), $n, $database);
    }
    
    public static function getAllBooks() {
        $result = parent::getDb ()->query("select substr (upper (sort), 1, 1) as title, count(*) as count
from books
group by substr (upper (sort), 1, 1)
order by substr (upper (sort), 1, 1)");
        $entryArray = array();
        while ($post = $result->fetchObject ())
        {
            array_push ($entryArray, new Entry ($post->title, Book::getEntryIdByLetter ($post->title), 
                str_format (localize("bookword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ("?page=".parent::PAGE_ALL_BOOKS_LETTER."&id=". rawurlencode ($post->title)))));
        }
        return $entryArray;
    }
    
    public static function getBooksByStartingLetter($letter, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_FIRST_LETTER, array ($letter . "%"), $n);
    }
    
    public static function getEntryArray ($query, $params, $n, $database = NULL) {
        list ($totalNumber, $result) = parent::executeQuery ($query, self::BOOK_COLUMNS, self::getFilterString (), $params, $n, $database);
        $entryArray = array();
        while ($post = $result->fetchObject ())
        {
            $book = new Book ($post);
            array_push ($entryArray, $book->getEntry ());
        }
        return array ($entryArray, $totalNumber);
    }

    
    public static function getAllRecentBooks() {
        global $config;
        list ($entryArray, $totalNumber) = self::getEntryArray (self::SQL_BOOKS_RECENT . $config['cops_recentbooks_limit'], array (), -1);
        return $entryArray;
    }

}
?>
