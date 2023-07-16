<?php
/**
 * COPS (Calibre OPDS PHP Server) test file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require(dirname(__FILE__) . "/../epubfs.php");
require(dirname(__FILE__) . "/config_test.php");
use PHPUnit\Framework\TestCase;
use SebLucas\Cops\Calibre\Book;
use SebLucas\EPubMeta\EPub;

use function SebLucas\Cops\Output\EPubReader\getComponentContent;

use const SebLucas\Cops\Config\COPS_ENDPOINTS;

class EpubFsTest extends TestCase
{
    private static $book;
    private static $add;


    public static function setUpBeforeClass(): void
    {
        $idData = 20;
        self::$add = "data=$idData&";
        $myBook = Book::getBookByDataId($idData);

        self::$book = new EPub($myBook->getFilePath("EPUB", $idData));
        self::$book->initSpineComponent();
    }

    public function testUrlImage()
    {
        $data = getComponentContent(self::$book, "cover.xml", self::$add);

        $src = "";
        if (preg_match("/src\=\'(.*?)\'/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=images~SLASH~cover.png', $src);
    }

    public function testUrlHref()
    {
        $data = getComponentContent(self::$book, "title.xml", self::$add);

        $src = "";
        if (preg_match("/src\=\'(.*?)\'/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=images~SLASH~logo~DASH~feedbooks~DASH~tiny.png', $src);

        $href = "";
        if (preg_match("/href\=\'(.*?)\'/", $data, $matches)) {
            $href = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=css~SLASH~title.css', $href);
    }

    public function testImportCss()
    {
        $data = getComponentContent(self::$book, "css~SLASH~title.css", self::$add);

        $import = "";
        if (preg_match("/import \'(.*?)\'/", $data, $matches)) {
            $import = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=css~SLASH~page.css', $import);
    }

    public function testUrlInCss()
    {
        $data = getComponentContent(self::$book, "css~SLASH~main.css", self::$add);

        $src = "";
        if (preg_match("/url\s*\(\'(.*?)\'\)/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&comp=fonts~SLASH~times.ttf', $src);
    }

    public function testDirectLink()
    {
        $data = getComponentContent(self::$book, "main10.xml", self::$add);

        $src = "";
        if (preg_match("/href\='(.*?)' title=\"Direct Link\"/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=main2.xml', $src);
    }

    public function testDirectLinkWithAnchor()
    {
        $data = getComponentContent(self::$book, "main10.xml", self::$add);

        $src = "";
        if (preg_match("/href\='(.*?)' title=\"Direct Link with anchor\"/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals(COPS_ENDPOINTS["epubfs"] . '?data=20&amp;comp=main2.xml#anchor', $src);
    }

    public function testAnchorOnly()
    {
        $data = getComponentContent(self::$book, "main10.xml", self::$add);

        $src = "";
        if (preg_match("/href\='(.*?)' title=\"Link to anchor\"/", $data, $matches)) {
            $src = $matches [1];
        }
        $this->assertEquals('#anchor', $src);
    }
}
