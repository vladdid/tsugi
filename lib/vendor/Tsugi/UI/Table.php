<?php

namespace Tsugi\UI;

use \Tsugi\UI\CrudForm;

/**
 * Our class to generate pageable tables.
 *
 * This handles all the details for generating pageable, sortable tables
 * that from a particular database query.
 *
 * This is a pretty complex class and for now the best way to understand
 * it is to look at its use in various places throughout the code.
 *
 * This also interoperates with the CrudForm class in those cases where
 * a table needs links to a detail page for a row as seen in
 * core/key/index.php
 *
 * @todo This is still emergent and as new use cases are encountered it
 * will likely evolve.
 */
class Table {

    public static $DEFAULT_PAGE_LENGTH = 20;  // Setting this to 2 is good for debugging

    public static function doForm($values, $override=Array()) {
        foreach (array_merge($values,$override) as $key => $value) {
            if ( $value === false ) continue;
            if ( is_string($value) && strlen($value) < 1 ) continue;
            if ( is_int($value) && $value === 0 ) continue;
            echo('<input type="hidden" name="'.htmlent_utf8($key).
                 '" value="'.htmlent_utf8($value).'">'."\n");
        }
    }

    public static function doUrl($values, $override=Array()) {
        $retval = '';
        foreach (array_merge($values,$override) as $key => $value) {
            if ( $value === false ) continue;
            if ( is_string($value) && strlen($value) < 1 ) continue;
            if ( is_int($value) && $value === 0 ) continue;
            if ( strlen($retval) > 0 ) $retval .= '&';
            $retval .= urlencode($key) . "=" . urlencode($value);
        }
        return $retval;
    }

    // Function to lookup and match things like R.updated_at to updated_at
    public static function matchColumns($colname, $columns) {
        foreach ($columns as $v) {
            if ( $colname == $v ) return true;
            if ( strlen($v) < 2 ) continue;
            if ( substr($v,1,1) != '.' ) continue;
            if ( substr($v,2) == $colname ) return true;
        }
        return false;
    }

    // Requires the keyword WHERE to be upper case - if a query has more than one WHERE clause
    // they should all be lower case except the one where the LIKE clauses will be added.

    // We will add the ORDER BY clause at the end using the first field in $orderfields
    // is there is not a 'order_by' in $params

    // Normally $params should just default to $_GET
    public static function pagedQuery($sql, &$queryvalues, $searchfields=array(), $orderfields=false, $params=false) {
        if ( $params == false ) $params = $_GET;
        if ( $orderfields == false ) $orderfields = $searchfields;

        $searchtext = '';
        if ( count($searchfields) > 0 && isset($params['search_text']) ) {
            for($i=0; $i < count($searchfields); $i++ ) {
                if ( $i > 0 ) $searchtext .= " OR ";
                $searchtext .= $searchfields[$i]." LIKE :SEARCH".$i;
                $queryvalues[':SEARCH'.$i] = '%'.$params['search_text'].'%';
            }
        }

        $ordertext = '';
        if ( isset($params['order_by']) && Table::matchColumns($params['order_by'], $orderfields) ) {
            $ordertext = $params['order_by']." ";
            if ( isset($params['desc']) && $params['desc'] == 1) {
                $ordertext .= "DESC ";
            }
        } else if ( count($orderfields) > 0 ) {
            $ordertext = $orderfields[0]." ";
        }

        $page_start = isset($params['page_start']) ? $params['page_start']+0 : 0;
        if ( $page_start < 0 ) $page_start = 0;
        $page_length = isset($params['page_length']) ? $params['page_length']+0 : self::$DEFAULT_PAGE_LENGTH;
        if ( $page_length < 0 ) $page_length = 0;

        $desc = '';
        if ( isset($params['desc']) ) {
            $desc = $params['desc']+0;
        }

        $limittext = '';
        if ( $page_start < 1 ) {
            $limittext = "".($page_length+1);
        } else {
            $limittext = "".$page_start.", ".($page_length+1);
        }

        // Fix up the SQL Query
        $newsql = $sql;
        if ( strlen($searchtext) > 0 ) {
            if ( strpos($sql,"WHERE" ) !== false ) {
                $newsql = str_replace("WHERE", "WHERE ( ".$searchtext." ) AND ", $newsql);
            } else {
                $newsql .= "\nWHERE ( ".$searchtext." ) ";
            }
        }
        if ( strlen($ordertext) > 0 ) {
            $newsql .= "\nORDER BY ".$ordertext." ";
        }
        if ( strlen($limittext) > 0 ) {
            $newsql .= "\nLIMIT ".$limittext." ";
        }
        return $newsql . "\n";
    }

    public static function pagedTable($rows, $searchfields=array(), $orderfields=false, $view=false, $params=false) {
        if ( $params === false ) $params = $_GET;
        if ( $orderfields === false ) $orderfields = $searchfields;

        $page_start = isset($params['page_start']) ? $params['page_start']+0 : 0;
        if ( $page_start < 0 ) $page_start = 0;
        $page_length = isset($params['page_length']) ? $params['page_length']+0 : self::$DEFAULT_PAGE_LENGTH;
        if ( $page_length < 0 ) $page_length = 0;

        $search = '';
        if ( isset($params['search_text']) ) {
            $search = $params['search_text'];
        }

        $count = count($rows);
        $have_more = false;
        if ( $count > $page_length ) {
            $have_more = true;
            $count = $page_length;
        }

        echo('<div style="float:right">');
        if ( $page_start > 0 ) {
            echo('<form style="display: inline">');
            echo('<input type="submit" value="Back" class="btn btn-default">');
            $page_back = $page_start - $page_length;
            if ( $page_back < 0 ) $page_back = 0;
            Table::doForm($params,Array('page_start' => $page_back));
            echo("</form>\n");
        }
        if ( $have_more ) {
            echo('<form style="display: inline">');
            echo('<input type="submit" value="Next" class="btn btn-default"> ');
            $page_next = $page_start + $page_length;
            Table::doForm($params,Array('page_start' => $page_next));
            echo("</form>\n");
        }
        echo("</div>\n");
        echo('<form>');
        echo('<input type="text" id="paged_search_box" value="'.htmlent_utf8($search).'" name="search_text">');
        Table::doForm($params,Array('search_text' => false, 'page_start' => false));
    ?>
    <input type="submit" value="Search" class="btn btn-default">
    <input type="submit" value="Clear Search" class="btn btn-default"
    onclick="document.getElementById('paged_search_box').value = '';"
    >
    </form>
    <?php
        if ( $count < 1 ) {
            echo("<p>Nothing to display.</p>\n");
            return;
        }
    // print_r($orderfields);
    // echo("<hr>\n");
    // print_r($rows[0]);
    ?>

    <div style="padding:3px;">
    <table border="1" class="table table-hover table-condensed table-responsive">
    <tr>
    <?php

        $first = true;
        $thispage = basename($_SERVER['PHP_SELF']);
        if ( $view === false ) $view = $thispage;
        foreach ( $rows as $row ) {
            $count--;
            if ( $count < 0 ) break;
            if ( $first ) {
                echo("\n<tr>\n");
                $desc = isset($params['desc']) ? $params['desc'] + 0 : 0;
                $order_by = isset($params['order_by']) ? $params['order_by'] : '';
                foreach($row as $k => $v ) {
                    if ( strpos($k, "_") === 0 ) continue;
                    if ( $view !== false && strpos($k, "_id") !== false && is_numeric($v) ) {
                        continue;
                    }

                    if ( ! Table::matchColumns($k, $orderfields ) ) {
                        echo("<th>".CrudForm::fieldToTitle($k)."</th>\n");
                        continue;
                    }

                    $override = Array('order_by' => $k, 'desc' => 0, 'page_start' => false);
                    $d = $desc;
                    $color = "black";
                    if ( $k == $order_by || $order_by == '' && $k == 'id' ) {
                        $d = ($desc + 1) % 2;
                        $override['desc'] = $d;
                        $color = $d == 1 ?  'green' : 'red';
                    }
                    $stuff = Table::doUrl($params,$override);
                    echo('<th>');
                    echo(' <a href="'.$thispage);
                    if ( strlen($stuff) > 0 ) {
                        echo("?");
                        echo($stuff);
                    }
                    echo('" style="color: '.$color.'">');
                    echo(ucwords(str_replace('_',' ',$k)));
                    echo("</a></th>\n");
                }
                echo("</tr>\n");
            }

            $first = false;
            $link_name = false;
            echo("<tr>\n");
            foreach($row as $k => $v ) {
                if ( strpos($k, "_") === 0 ) continue;
                if ( $view !== false && strpos($k, "_id") !== false && is_numeric($v) ) {
                    $link_name = $k;
                    $link_val = $v;
                    continue;
                }
                echo("<td>");
                if ( $link_name !== false ) {
                    echo('<a href="'.$view.'?'.$link_name."=".$link_val.'">');
                    if ( strlen($v) < 1 ) $v = $link_name.':'.$link_val;
                }
                echo(htmlent_utf8($v));
                if ( $link_name !== false ) {
                    echo('</a>');
                }
                $link_name = false;
                echo("</td>\n");
            }
            echo("</tr>\n");
        }
        echo("</table>\n");
        echo("</div>\n");
    }

    public static function pagedAuto($sql, $query_parms, $searchfields,
        $orderfields=false, $view=false, $params=false) {
        global $PDOX;

        $newsql = Table::pagedQuery($sql, $query_parms, $searchfields, $orderfields, $params);

        //echo("<pre>\n$newsql\n</pre>\n");

        $rows = $PDOX->allRowsDie($newsql, $query_parms);

        Table::pagedTable($rows, $searchfields, $orderfields, $view, $params);
    }

}
