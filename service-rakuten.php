<?php
/**
 * アフィリエイトサービス実装クラス(楽天)
 * User: cottonspace
 * Date: 12/04/11
 */

/**
 * 基底クラス
 */
require_once "service-base.php";

/**
 * アフィリエイトサービスの実装クラス
 */
class Rakuten extends ServiceBase
{
    /**
     * 商品検索ページ総数
     * @var int 商品検索ページ総数
     */
    private $pageCount;

    /**
     * 商品検索ソート方法
     * @var array 商品ソート指定の配列
     */
    private $sortTypes = array(
        "+price" => "+itemPrice",
        "-price" => "-itemPrice",
        "-reviews" => "-reviewCount",
        "+reviews" => "+reviewCount",
        "-score" => "-reviewAverage",
        "+score" => "+reviewAverage"
    );

    /**
     * 価格表示フォーマット処理
     * @param string $price 商品価格
     * @param string $tax 消費税区分
     * @param string $postage 送料区分
     * @return string 価格表示文字列
     */
    private function formatPrice($price, $tax, $postage)
    {
        $ret = "";
        if (!empty($price)) {
            $ret = number_format(floatval($price)) . " 円";
            if ($tax === "0") {
                $ret .= " (税込)";
            }
            if ($postage === "0") {
                $ret .= " 送料込";
            }
        }
        return $ret;
    }

    /**
     * カテゴリ検索クエリ生成
     * @link http://webservice.rakuten.co.jp/api/genresearch/
     * @param string $parent 対象カテゴリ
     * @return string RESTクエリ文字列
     */
    private function queryCategories($parent)
    {
        if (empty($parent)) {
            $parent = 0;
        }
        $baseurl = "http://api.rakuten.co.jp/rws/3.0/rest";
        $params = array();
        $params["developerId"] = $this->account["developerId"];
        $params["affiliateId"] = $this->account["affiliateId"];
        $params["operation"] = "GenreSearch";
        $params["version"] = "2007-04-11";
        $params["genrePath"] = 0;
        $params["genreId"] = $parent;
        ksort($params);
        return $baseurl . "?" . http_build_query($params);
    }

    /**
     * 商品検索クエリ生成
     * @link http://webservice.rakuten.co.jp/api/itemsearch/
     * @param array $search 商品検索条件
     * @return string RESTクエリ文字列
     */
    private function queryItems(&$search)
    {
        $baseurl = "http://api.rakuten.co.jp/rws/3.0/rest";
        $params = array();
        $params["developerId"] = $this->account["developerId"];
        $params["affiliateId"] = $this->account["affiliateId"];
        $params["operation"] = "ItemSearch";
        $params["version"] = "2010-09-15";
        $params["hits"] = empty($search["pagesize"]) ? 10 : $search["pagesize"];
        $params["availability"] = 1;
        $params["field"] = 1;
        $params["carrier"] = empty($search["mobile"]) ? 0 : 1;
        $params["imageFlag"] = 1;
        $params["purchaseType"] = 0;
        $params["genreId"] = empty($search["category"]) ? "0" : $search["category"];
        if (!empty($search["keyword"])) {
            $params["keyword"] = $search["keyword"];
        }
        if (!empty($search["shop"])) {
            $params["shopCode"] = $search["shop"];
        }
        if (!empty($search["sort"]) && array_key_exists($search["sort"], $this->sortTypes)) {
            $params["sort"] = $this->sortTypes[$search["sort"]];
        } else {
            $params["sort"] = "standard";
        }
        $params["page"] = $search["page"];
        ksort($params);
        return $baseurl . "?" . http_build_query($params);
    }

    /**
     * サービス識別名
     * @return string サービス識別名
     */
    public function serviceName()
    {
        return "rakuten";
    }

    /**
     * サービスクレジット表記
     * @return string サービスクレジット表記
     */
    public function serviceCredit()
    {
        $credit = <<<EOF
<!-- Rakuten Web Services Attribution Snippet FROM HERE -->
<a href="http://webservice.rakuten.co.jp/" target="_blank"><img src="http://webservice.rakuten.co.jp/img/credit/200709/credit_4936.gif" border="0" alt="楽天ウェブサービスセンター" title="楽天ウェブサービスセンター" width="49" height="36"/></a>
<!-- Rakuten Web Services Attribution Snippet TO HERE -->\n
EOF;
        return $credit;
    }

    /**
     * 商品検索ソート方法取得
     * @param string $category 検索対象のカテゴリ名
     * @return array ソート指定の連想配列
     */
    public function getSortTypes($category = "")
    {
        return $this->sortTypes;
    }

    /**
     * 商品検索ページ総数
     * @return int 商品検索ページ総数
     */
    public function getPageCount()
    {
        return $this->pageCount;
    }

    /**
     * カテゴリ検索
     * @link http://webservice.rakuten.co.jp/api/genresearch/
     * @param string $parent 基底カテゴリ
     * @return array カテゴリ情報の連想配列
     */
    public function getCategories($parent = "")
    {
        if (empty($parent)) {
            $parent = 0;
        }

        // RESTクエリ情報を取得
        $query = $this->queryCategories($parent);

        // RESTクエリ実行
        $strxml = $this->download($query, $query);
        $strxml = str_replace("header:Header", "Header", $strxml);
        $strxml = str_replace("genreSearch:GenreSearch", "GenreSearch", $strxml);
        $objxml = simplexml_load_string($strxml);
        $hash = array();
        if (isset($objxml->Body->GenreSearch)) {
            foreach ($objxml->Body->GenreSearch->child as $node) {
                $hash[(string)$node->genreId] = (string)$node->genreName;
            }
        }
        return $hash;
    }

    /**
     * 商品検索
     * @link http://webservice.rakuten.co.jp/api/itemsearch/
     * @param array $search 商品検索条件
     * @return array 商品情報の連想配列
     */
    public function getItems(&$search)
    {
        // RESTクエリ情報を取得
        $query = $this->queryItems($search);

        // RESTクエリ実行
        $strxml = $this->download($query, $query);
        $strxml = str_replace("header:Header", "Header", $strxml);
        $strxml = str_replace("itemSearch:ItemSearch", "ItemSearch", $strxml);
        $objxml = simplexml_load_string($strxml);
        $hash = array();
        if (isset($objxml->Body->ItemSearch)) {
            $this->pageCount = intval($objxml->Body->ItemSearch->pageCount);
            foreach ($objxml->Body->ItemSearch->Items->Item as $node) {
                array_push($hash, array(
                        "name" => (string)$node->itemName,
                        "price" => $this->formatPrice((string)$node->itemPrice, (string)$node->taxFlag, (string)$node->postageFlag),
                        "desc" => (string)$node->itemCaption,
                        "shop" => (string)$node->shopName,
                        "score"=> floatval((string)$node->reviewAverage),
                        "aurl" => (string)$node->affiliateUrl,
                        "iurl" => empty($search["mobile"]) ? (string)$node->mediumImageUrl : (string)$node->smallImageUrl,
                        "surl" => (string)$node->shopUrl)
                );
            }
        } else {
            $this->pageCount = 0;
        }
        return $hash;
    }
}

?>
