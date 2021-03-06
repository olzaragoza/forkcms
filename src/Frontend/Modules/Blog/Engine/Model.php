<?php

namespace Frontend\Modules\Blog\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Frontend\Core\Engine\Language as FL;
use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Core\Engine\Navigation as FrontendNavigation;
use Frontend\Core\Engine\Url as FrontendURL;
use Frontend\Modules\Tags\Engine\Model as FrontendTagsModel;
use Frontend\Modules\Tags\Engine\TagsInterface as FrontendTagsInterface;

/**
 * In this file we store all generic functions that we will be using in the blog module
 *
 * @author Davy Hellemans <davy.hellemans@netlash.com>
 * @author Dave Lens <dave.lens@netlash.com>
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Annelies Van Extergem <annelies.vanextergem@netlash.com>
 * @author Matthias Mullie <forkcms@mullie.eu>
 * @author Dieter Vanden Eynde <dieter.vandeneynde@netlash.com>
 */
class Model implements FrontendTagsInterface
{
    /**
     * Get an item
     *
     * @param string $URL The URL for the item.
     *
     * @return array
     */
    public static function get($URL)
    {
        $return = (array) FrontendModel::getContainer()->get('database')->getRecord(
            'SELECT i.id, i.revision_id, i.language, i.title, i.introduction, i.text,
             c.title AS category_title, m2.url AS category_url, i.image,
             UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.user_id,
             i.allow_comments,
             m.keywords AS meta_keywords, m.keywords_overwrite AS meta_keywords_overwrite,
             m.description AS meta_description, m.description_overwrite AS meta_description_overwrite,
             m.title AS meta_title, m.title_overwrite AS meta_title_overwrite,
             m.url,
             m.data AS meta_data
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ? AND m.url = ?
             LIMIT 1',
            array('active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00', (string) $URL)
        );

        // unserialize
        if (isset($return['meta_data'])) {
            $return['meta_data'] = @unserialize($return['meta_data']);
        }

        // image?
        if (isset($return['image'])) {
            $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

            foreach ($folders as $folder) {
                $return['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] . '/' . $return['image'];
            }
        }

        // return
        return $return;
    }

    /**
     * Get all items (at least a chunk)
     *
     * @param int $limit  The number of items to get.
     * @param int $offset The offset.
     *
     * @return array
     */
    public static function getAll($limit = 10, $offset = 0)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.revision_id, i.language, i.title, i.introduction, i.text, i.num_comments AS comments_count,
             c.title AS category_title, m2.url AS category_url, i.image,
             UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.user_id, i.allow_comments,
             m.url
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ?
             ORDER BY i.publish_on DESC, i.id DESC
             LIMIT ?, ?',
            array(
                'active',
                FRONTEND_LANGUAGE,
                'N',
                FrontendModel::getUTCDate('Y-m-d H:i') . ':00',
                (int) $offset,
                (int) $limit,
            ),
            'id'
        );

        // no results?
        if (empty($items)) {
            return array();
        }

        // init var
        $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');
        $categoryLink = FrontendNavigation::getURLForBlock('Blog', 'Category');
        $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

        // loop
        foreach ($items as $key => $row) {
            // URLs
            $items[$key]['full_url'] = $link . '/' . $row['url'];
            $items[$key]['category_full_url'] = $categoryLink . '/' . $row['category_url'];

            // comments
            if ($row['comments_count'] > 0) {
                $items[$key]['comments'] = true;
            }
            if ($row['comments_count'] > 1) {
                $items[$key]['comments_multiple'] = true;
            }

            // allow comments as boolean
            $items[$key]['allow_comments'] = ($row['allow_comments'] == 'Y');

            // reset allow comments
            if (!FrontendModel::get('fork.settings')->get('Blog', 'allow_comments')) {
                $items[$key]['allow_comments'] = false;
            }

            // image?
            if (isset($row['image'])) {
                foreach ($folders as $folder) {
                    $items[$key]['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] . '/' . $row['image'];
                }
            }
        }

        // get all tags
        $tags = FrontendTagsModel::getForMultipleItems('Blog', array_keys($items));

        // loop tags and add to correct item
        foreach ($tags as $postId => $data) {
            if (isset($items[$postId])) {
                $items[$postId]['tags'] = $data;
            }
        }

        // return
        return $items;
    }

    /**
     * Get all categories used
     *
     * @return array
     */
    public static function getAllCategories()
    {
        $return = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT c.id, c.title AS label, m.url, COUNT(c.id) AS total, m.data AS meta_data
             FROM blog_categories AS c
             INNER JOIN blog_posts AS i ON c.id = i.category_id AND c.language = i.language
             INNER JOIN meta AS m ON c.meta_id = m.id
             WHERE c.language = ? AND i.status = ? AND i.hidden = ? AND i.publish_on <= ?
             GROUP BY c.id',
            array(FRONTEND_LANGUAGE, 'active', 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00'),
            'id'
        );

        // loop items and unserialize
        foreach ($return as &$row) {
            if (isset($row['meta_data'])) {
                $row['meta_data'] = @unserialize($row['meta_data']);
            }
        }

        return $return;
    }

    /**
     * Get all comments (at least a chunk)
     *
     * @param int $limit  The number of items to get.
     * @param int $offset The offset.
     *
     * @return array
     */
    public static function getAllComments($limit = 10, $offset = 0)
    {
        return (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, UNIX_TIMESTAMP(i.created_on) AS created_on, i.author, i.text,
             p.id AS post_id, p.title AS post_title, m.url AS post_url
             FROM blog_comments AS i
             INNER JOIN blog_posts AS p ON i.post_id = p.id AND i.language = p.language
             INNER JOIN meta AS m ON p.meta_id = m.id
             WHERE i.status = ? AND i.language = ?
             GROUP BY i.id
             ORDER BY i.created_on DESC
             LIMIT ?, ?',
            array('published', FRONTEND_LANGUAGE, (int) $offset, (int) $limit)
        );
    }

    /**
     * Get the number of items
     *
     * @return int
     */
    public static function getAllCount()
    {
        return (int) FrontendModel::getContainer()->get('database')->getVar(
            'SELECT COUNT(i.id) AS count
             FROM blog_posts AS i
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ?',
            array('active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00')
        );
    }

    /**
     * Get all items in a category (at least a chunk)
     *
     * @param string $categoryURL The URL of the category to retrieve the posts for.
     * @param int    $limit       The number of items to get.
     * @param int    $offset      The offset.
     *
     * @return array
     */
    public static function getAllForCategory($categoryURL, $limit = 10, $offset = 0)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.revision_id, i.language, i.title, i.introduction, i.text, i.num_comments AS comments_count,
             c.title AS category_title, m2.url AS category_url, i.image,
             UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.user_id, i.allow_comments,
             m.url
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ? AND m2.url = ?
             ORDER BY i.publish_on DESC
             LIMIT ?, ?',
            array(
                'active',
                FRONTEND_LANGUAGE,
                'N',
                FrontendModel::getUTCDate('Y-m-d H:i') . ':00',
                (string) $categoryURL,
                (int) $offset,
                (int) $limit,
            ),
            'id'
        );

        // no results?
        if (empty($items)) {
            return array();
        }

        // init var
        $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');
        $categoryLink = FrontendNavigation::getURLForBlock('Blog', 'Category');
        $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

        // loop
        foreach ($items as $key => $row) {
            // URLs
            $items[$key]['full_url'] = $link . '/' . $row['url'];
            $items[$key]['category_full_url'] = $categoryLink . '/' . $row['category_url'];

            // comments
            if ($row['comments_count'] > 0) {
                $items[$key]['comments'] = true;
            }
            if ($row['comments_count'] > 1) {
                $items[$key]['comments_multiple'] = true;
            }

            // allow comments as boolean
            $items[$key]['allow_comments'] = ($row['allow_comments'] == 'Y');

            // reset allow comments
            if (!FrontendModel::get('fork.settings')->get('Blog', 'allow_comments')) {
                $items[$key]['allow_comments'] = false;
            }

            // image?
            if (isset($row['image'])) {
                foreach ($folders as $folder) {
                    $items[$key]['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] .
                                                                  '/' . $row['image'];
                }
            }
        }

        // get all tags
        $tags = FrontendTagsModel::getForMultipleItems('Blog', array_keys($items));

        // loop tags and add to correct item
        foreach ($tags as $postId => $data) {
            $items[$postId]['tags'] = $data;
        }

        // return
        return $items;
    }

    /**
     * Get the number of items in a given category
     *
     * @param string $URL The URL for the category.
     *
     * @return int
     */
    public static function getAllForCategoryCount($URL)
    {
        return (int) FrontendModel::getContainer()->get('database')->getVar(
            'SELECT COUNT(i.id) AS count
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON c.meta_id = m.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ? AND m.url = ?',
            array('active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00', (string) $URL)
        );
    }

    /**
     * Get all items between a start and end-date
     *
     * @param int $start  The start date as a UNIX-timestamp.
     * @param int $end    The end date as a UNIX-timestamp.
     * @param int $limit  The number of items to get.
     * @param int $offset The offset.
     *
     * @return array
     */
    public static function getAllForDateRange($start, $end, $limit = 10, $offset = 0)
    {
        $start = (int) $start;
        $end = (int) $end;
        $limit = (int) $limit;
        $offset = (int) $offset;

        // get the items
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.revision_id, i.language, i.title, i.introduction, i.text, i.num_comments AS comments_count,
             c.title AS category_title, m2.url AS category_url, i.image,
             UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.user_id, i.allow_comments,
             m.url
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on BETWEEN ? AND ?
             ORDER BY i.publish_on DESC
             LIMIT ?, ?',
            array(
                'active',
                FRONTEND_LANGUAGE,
                'N',
                FrontendModel::getUTCDate('Y-m-d H:i', $start),
                FrontendModel::getUTCDate('Y-m-d H:i', $end),
                $offset,
                $limit,
            ),
            'id'
        );

        // no results?
        if (empty($items)) {
            return array();
        }

        // init var
        $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');
        $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

        // loop
        foreach ($items as $key => $row) {
            // URLs
            $items[$key]['full_url'] = $link . '/' . $row['url'];

            // comments
            if ($row['comments_count'] > 0) {
                $items[$key]['comments'] = true;
            }
            if ($row['comments_count'] > 1) {
                $items[$key]['comments_multiple'] = true;
            }

            // allow comments as boolean
            $items[$key]['allow_comments'] = ($row['allow_comments'] == 'Y');

            // reset allow comments
            if (!FrontendModel::get('fork.settings')->get('Blog', 'allow_comments')) {
                $items[$key]['allow_comments'] = false;
            }

            // image?
            if (isset($row['image'])) {
                foreach ($folders as $folder) {
                    $items[$key]['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] .
                                                                  '/' . $row['image'];
                }
            }
        }

        // get all tags
        $tags = FrontendTagsModel::getForMultipleItems('Blog', array_keys($items));

        // loop tags and add to correct item
        foreach ($tags as $postId => $data) {
            $items[$postId]['tags'] = $data;
        }

        // return
        return $items;
    }

    /**
     * Get the number of items in a date range
     *
     * @param int $start The start date as a UNIX-timestamp.
     * @param int $end   The end date as a UNIX-timestamp.
     *
     * @return int
     */
    public static function getAllForDateRangeCount($start, $end)
    {
        $start = (int) $start;
        $end = (int) $end;

        // return the number of items
        return (int) FrontendModel::getContainer()->get('database')->getVar(
            'SELECT COUNT(i.id)
             FROM blog_posts AS i
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on BETWEEN ? AND ?',
            array(
                'active',
                FRONTEND_LANGUAGE,
                'N',
                FrontendModel::getUTCDate('Y-m-d H:i:s', $start),
                FrontendModel::getUTCDate('Y-m-d H:i:s', $end),
            )
        );
    }

    /**
     * Get the statistics for the archive
     *
     * @return array
     */
    public static function getArchiveNumbers()
    {
        // grab stats
        $numbers = FrontendModel::getContainer()->get('database')->getPairs(
            'SELECT DATE_FORMAT(i.publish_on, "%Y%m") AS month, COUNT(i.id)
             FROM blog_posts AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ?
             GROUP BY month',
            array('active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00')
        );

        // init vars
        $stats = array();
        $link = FrontendNavigation::getURLForBlock('Blog', 'Archive');
        $firstYear = (int) date('Y');
        $lastYear = 0;

        // loop the numbers
        foreach ($numbers as $key => $count) {
            // init vars
            $year = mb_substr($key, 0, 4);
            $month = mb_substr($key, 4, 2);

            // reset
            if ($year < $firstYear) {
                $firstYear = $year;
            }
            if ($year > $lastYear) {
                $lastYear = $year;
            }

            // generate timestamp
            $timestamp = gmmktime(00, 00, 00, $month, 01, $year);

            // initialize if needed
            if (!isset($stats[$year])) {
                $stats[$year] = array(
                    'url' => $link . '/' . $year,
                    'label' => $year,
                    'total' => 0,
                    'months' => null,
                );
            }

            // increment the total
            $stats[$year]['total'] += (int) $count;
            $stats[$year]['months'][$key] = array(
                'url' => $link . '/' . $year . '/' . $month,
                'label' => $timestamp,
                'total' => $count,
            );
        }

        // loop years
        for ($i = $firstYear; $i <= $lastYear; ++$i) {
            // year missing
            if (!isset($stats[$i])) {
                $stats[$i] = array('url' => null, 'label' => $i, 'total' => 0, 'months' => null);
            }
        }

        // sort
        krsort($stats);

        // reset stats
        foreach ($stats as &$row) {
            // remove url for empty years
            if ($row['total'] == 0) {
                $row['url'] = null;
            }

            // any months?
            if (!empty($row['months'])) {
                // sort months
                ksort($row['months']);
            }
        }

        // return
        return $stats;
    }

    /**
     * Get the comments for an item
     *
     * @param int $id The ID of the item to get the comments for.
     *
     * @return array
     */
    public static function getComments($id)
    {
        // get the comments
        $comments = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT c.id, UNIX_TIMESTAMP(c.created_on) AS created_on, c.text, c.data,
             c.author, c.email, c.website
             FROM blog_comments AS c
             WHERE c.post_id = ? AND c.status = ? AND c.language = ?
             ORDER BY c.id ASC',
            array((int) $id, 'published', FRONTEND_LANGUAGE)
        );

        // loop comments and create gravatar id
        foreach ($comments as &$row) {
            $row['gravatar_id'] = md5($row['email']);
        }

        // return
        return $comments;
    }

    /**
     * Fetch the list of tags for a list of items
     *
     * @param array $ids The ids of the items to grab.
     *
     * @return array
     */
    public static function getForTags(array $ids)
    {
        // fetch items
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.title, m.url
             FROM blog_posts AS i
             INNER JOIN meta AS m ON m.id = i.meta_id
             WHERE i.status = ? AND i.hidden = ? AND i.id IN (' . implode(',', $ids) . ')
             ORDER BY i.publish_on DESC',
            array('active', 'N')
        );

        // has items
        if (!empty($items)) {
            // init var
            $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');
            $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

            // reset url
            foreach ($items as &$row) {
                $row['full_url'] = $link . '/' . $row['url'];

                // image?
                if (isset($row['image'])) {
                    foreach ($folders as $folder) {
                        $row['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] .
                                                              '/' . $row['image'];
                    }
                }
            }
        }

        // return
        return $items;
    }

    /**
     * Get the id of an item by the full URL of the current page.
     * Selects the proper part of the full URL to get the item's id from the database.
     *
     * @param FrontendURL $URL The current URL.
     *
     * @return int
     */
    public static function getIdForTags(FrontendURL $URL)
    {
        // select the proper part of the full URL
        $itemURL = (string) $URL->getParameter(1);

        // return the item
        return self::get($itemURL);
    }

    /**
     * Get an array with the previous and the next post
     *
     * @param int $id The id of the current item.
     *
     * @return array
     */
    public static function getNavigation($id)
    {
        // redefine
        $id = (int) $id;

        // get db
        $db = FrontendModel::getContainer()->get('database');

        // get date for current item
        $date = (string) $db->getVar(
            'SELECT i.publish_on
             FROM blog_posts AS i
             WHERE i.id = ? AND i.status = ?',
            array($id, 'active')
        );

        // validate
        if ($date == '') {
            return array();
        }

        // init var
        $navigation = array();
        $detailLink = FrontendNavigation::getURLForBlock('Blog', 'Detail') . '/';

        // get previous post
        $navigation['previous'] = $db->getRecord(
            'SELECT i.id, i.title, CONCAT(?, m.url) AS url
             FROM blog_posts AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.id != ? AND i.status = ? AND i.hidden = ? AND i.language = ? AND
                ((i.publish_on = ? AND i.id < ?) OR i.publish_on < ?)
             ORDER BY i.publish_on DESC, i.id DESC
             LIMIT 1',
            array($detailLink, $id, 'active', 'N', FRONTEND_LANGUAGE, $date, $id, $date)
        );

        // get next post
        $navigation['next'] = $db->getRecord(
            'SELECT i.id, i.title, CONCAT(?, m.url) AS url
             FROM blog_posts AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.id != ? AND i.status = ? AND i.hidden = ? AND i.language = ? AND
                ((i.publish_on = ? AND i.id > ?) OR i.publish_on > ?)
             ORDER BY i.publish_on ASC, i.id ASC
             LIMIT 1',
            array($detailLink, $id, 'active', 'N', FRONTEND_LANGUAGE, $date, $id, $date)
        );

        // if empty, unset it
        if (empty($navigation['previous'])) {
            unset($navigation['previous']);
        }
        if (empty($navigation['next'])) {
            unset($navigation['next']);
        }

        // return
        return $navigation;
    }

    /**
     * Get recent comments
     *
     * @param int $limit The number of comments to get.
     *
     * @return array
     */
    public static function getRecentComments($limit = 5)
    {
        $limit = (int) $limit;

        // init var
        $return = array();

        // get comments
        $comments = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT c.id, c.author, c.website, c.email, UNIX_TIMESTAMP(c.created_on) AS created_on, c.text,
             i.id AS post_id, i.title AS post_title,
             m.url AS post_url
             FROM blog_comments AS c
             INNER JOIN blog_posts AS i ON c.post_id = i.id AND c.language = i.language
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE c.status = ? AND i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ?
             ORDER BY c.id DESC
             LIMIT ?',
            array('published', 'active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00', $limit)
        );

        // validate
        if (empty($comments)) {
            return $return;
        }

        // get link
        $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');

        // loop comments
        foreach ($comments as &$row) {
            // add some URLs
            $row['post_full_url'] = $link . '/' . $row['post_url'];
            $row['full_url'] = $link . '/' . $row['post_url'] . '#comment-' . $row['id'];
            $row['gravatar_id'] = md5($row['email']);
        }

        return $comments;
    }

    /**
     * Get related items based on tags
     *
     * @param int $id    The id of the item to get related items for.
     * @param int $limit The maximum number of items to retrieve.
     *
     * @return array
     */
    public static function getRelated($id, $limit = 5)
    {
        $id = (int) $id;
        $limit = (int) $limit;

        // get the related IDs
        $relatedIDs = (array) FrontendTagsModel::getRelatedItemsByTags($id, 'Blog', 'Blog', $limit);

        // no items
        if (empty($relatedIDs)) {
            return array();
        }

        // get link
        $link = FrontendNavigation::getURLForBlock('Blog', 'Detail');

        // get items
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.title, m.url
             FROM blog_posts AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.status = ? AND i.language = ? AND i.hidden = ? AND i.publish_on <= ? AND i.id IN(' .
            implode(',', $relatedIDs) . ')
             ORDER BY i.publish_on DESC, i.id DESC
             LIMIT ?',
            array('active', FRONTEND_LANGUAGE, 'N', FrontendModel::getUTCDate('Y-m-d H:i') . ':00', $limit),
            'id'
        );

        // loop items
        foreach ($items as &$row) {
            $row['full_url'] = $link . '/' . $row['url'];
        }

        return $items;
    }

    /**
     * Get a revision for an item
     *
     * @param string $URL      The URL for the item to get.
     * @param int    $revision The revisionID.
     *
     * @return array
     */
    public static function getRevision($URL, $revision)
    {
        $return = (array) FrontendModel::getContainer()->get('database')->getRecord(
            'SELECT i.id, i.revision_id, i.language, i.title, i.introduction, i.text, i.image,
             c.title AS category_title, m2.url AS category_url,
             UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.user_id,
             i.allow_comments,
             m.keywords AS meta_keywords, m.keywords_overwrite AS meta_keywords_overwrite,
             m.description AS meta_description, m.description_overwrite AS meta_description_overwrite,
             m.title AS meta_title, m.title_overwrite AS meta_title_overwrite,
             m.url,
             m.data AS meta_data
             FROM blog_posts AS i
             INNER JOIN blog_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.language = ? AND i.revision_id = ? AND m.url = ?
             LIMIT 1',
            array(FRONTEND_LANGUAGE, (int) $revision, (string) $URL)
        );

        // unserialize
        if (isset($return['meta_data'])) {
            $return['meta_data'] = @unserialize($return['meta_data']);
        }

        // image?
        if (isset($return['image'])) {
            $folders = FrontendModel::getThumbnailFolders(FRONTEND_FILES_PATH . '/Blog/Images', true);

            foreach ($folders as $folder) {
                $return['image_' . $folder['dirname']] = $folder['url'] . '/' . $folder['dirname'] . '/' . $return['image'];
            }
        }

        // return
        return $return;
    }

    /**
     * Inserts a new comment
     *
     * @param array $comment The comment to add.
     *
     * @return int
     */
    public static function insertComment(array $comment)
    {
        // get db
        $db = FrontendModel::getContainer()->get('database');

        // insert comment
        $comment['id'] = (int) $db->insert('blog_comments', $comment);

        // recalculate if published
        if ($comment['status'] == 'published') {
            // num comments
            $numComments = (int) FrontendModel::getContainer()->get('database')->getVar(
                'SELECT COUNT(i.id) AS comment_count
                 FROM blog_comments AS i
                 INNER JOIN blog_posts AS p ON i.post_id = p.id AND i.language = p.language
                 WHERE i.status = ? AND i.post_id = ? AND i.language = ? AND p.status = ?
                 GROUP BY i.post_id',
                array('published', $comment['post_id'], FRONTEND_LANGUAGE, 'active')
            );

            // update num comments
            $db->update('blog_posts', array('num_comments' => $numComments), 'id = ?', $comment['post_id']);
        }

        return $comment['id'];
    }

    /**
     * Get moderation status for an author
     *
     * @param string $author The name for the author.
     * @param string $email  The email address for the author.
     *
     * @return bool
     */
    public static function isModerated($author, $email)
    {
        return (bool) FrontendModel::getContainer()->get('database')->getVar(
            'SELECT 1
             FROM blog_comments AS c
             WHERE c.status = ? AND c.author = ? AND c.email = ?
             LIMIT 1',
            array('published', (string) $author, (string) $email)
        );
    }

    /**
     * Notify the admin
     *
     * @param array $comment The comment that was submitted.
     */
    public static function notifyAdmin(array $comment)
    {
        // don't notify admin in case of spam
        if ($comment['status'] == 'spam') {
            return;
        }

        // build data for push notification
        if ($comment['status'] == 'moderation') {
            $key = 'BLOG_COMMENT_MOD';
        } else {
            $key = 'BLOG_COMMENT';
        }

        $author = $comment['author'];
        if (mb_strlen($author) > 20) {
            $author = mb_substr($author, 0, 19) . '…';
        }
        $text = $comment['text'];
        if (mb_strlen($text) > 50) {
            $text = mb_substr($text, 0, 49) . '…';
        }

        $alert = array(
            'loc-key' => $key,
            'loc-args' => array(
                $author,
                $text,
            ),
        );

        // build data
        $data = array(
            'api' => SITE_URL . '/api/1.0',
            'id' => $comment['id'],
        );

        // push it
        FrontendModel::pushToAppleApp($alert, null, 'default', $data);

        // get settings
        $notifyByMailOnComment = FrontendModel::get('fork.settings')->get('Blog', 'notify_by_email_on_new_comment', false);
        $notifyByMailOnCommentToModerate = FrontendModel::get('fork.settings')->get(
            'Blog',
            'notify_by_email_on_new_comment_to_moderate',
            false
        );

        // create URLs
        $URL = SITE_URL . FrontendNavigation::getURLForBlock('Blog', 'Detail') . '/' .
               $comment['post_url'] . '#comment-' . $comment['id'];
        $backendURL = SITE_URL . FrontendNavigation::getBackendURLForBlock('comments', 'Blog') . '#tabModeration';

        // notify on all comments
        if ($notifyByMailOnComment) {
            // init var
            $variables = null;

            // comment to moderate
            if ($comment['status'] == 'moderation') {
                $variables['message'] = vsprintf(
                    FL::msg('BlogEmailNotificationsNewCommentToModerate'),
                    array($comment['author'], $URL, $comment['post_title'], $backendURL)
                );
            } elseif ($comment['status'] == 'published') {
                // comment was published
                $variables['message'] = vsprintf(
                    FL::msg('BlogEmailNotificationsNewComment'),
                    array($comment['author'], $URL, $comment['post_title'])
                );
            }

            $to = FrontendModel::get('fork.settings')->get('Core', 'mailer_to');
            $from = FrontendModel::get('fork.settings')->get('Core', 'mailer_from');
            $replyTo = FrontendModel::get('fork.settings')->get('Core', 'mailer_reply_to');
            $message = \Common\Mailer\Message::newInstance(FL::msg('NotificationSubject'))
                ->setFrom(array($from['email'] => $from['name']))
                ->setTo(array($to['email'] => $to['name']))
                ->setReplyTo(array($replyTo['email'] => $replyTo['name']))
                ->parseHtml(
                    FRONTEND_CORE_PATH . '/Layout/Templates/Mails/Notification.html.twig',
                    $variables,
                    true
                )
            ;
            FrontendModel::get('mailer')->send($message);
        } elseif ($notifyByMailOnCommentToModerate && $comment['status'] == 'moderation') {
            // only notify on new comments to moderate and if the comment is one to moderate
            // set variables
            $variables['message'] = vsprintf(
                FL::msg('BlogEmailNotificationsNewCommentToModerate'),
                array($comment['author'], $URL, $comment['post_title'], $backendURL)
            );

            $to = FrontendModel::get('fork.settings')->get('Core', 'mailer_to');
            $from = FrontendModel::get('fork.settings')->get('Core', 'mailer_from');
            $replyTo = FrontendModel::get('fork.settings')->get('Core', 'mailer_reply_to');
            $message = \Common\Mailer\Message::newInstance(FL::msg('NotificationSubject'))
                ->setFrom(array($from['email'] => $from['name']))
                ->setTo(array($to['email'] => $to['name']))
                ->setReplyTo(array($replyTo['email'] => $replyTo['name']))
                ->parseHtml(
                    FRONTEND_CORE_PATH . '/Layout/Templates/Mails/Notification.html.twig',
                    $variables,
                    true
                )
            ;
            FrontendModel::get('mailer')->send($message);
        }
    }

    /**
     * Parse the search results for this module
     *
     * Note: a module's search function should always:
     *        - accept an array of entry id's
     *        - return only the entries that are allowed to be displayed, with their array's index being the entry's id
     *
     *
     * @param array $ids The ids of the found results.
     *
     * @return array
     */
    public static function search(array $ids)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.title, i.introduction, i.text, m.url
             FROM blog_posts AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.status = ? AND i.hidden = ? AND i.language = ? AND i.publish_on <= ? AND i.id IN (' .
            implode(',', $ids) . ')',
            array('active', 'N', FRONTEND_LANGUAGE, date('Y-m-d H:i') . ':00'),
            'id'
        );

        // prepare items for search
        $detailUrl = FrontendNavigation::getURLForBlock('Blog', 'Detail');
        foreach ($items as &$item) {
            $item['full_url'] = $detailUrl . '/' . $item['url'];
        }

        // return
        return $items;
    }
}
