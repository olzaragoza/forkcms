<?php

namespace Frontend\Modules\Faq\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Core\Engine\Navigation as FrontendNavigation;
use Frontend\Core\Engine\Url as FrontendURL;
use Frontend\Modules\Tags\Engine\Model as FrontendTagsModel;
use Frontend\Modules\Tags\Engine\TagsInterface as FrontendTagsInterface;

/**
 * In this file we store all generic functions that we will be using in the faq module
 *
 * @author Lester Lievens <lester.lievens@netlash.com>
 * @author Jelmer Snoeck <jelmer@siphoc.com>
 */
class Model implements FrontendTagsInterface
{
    /**
     * Fetch a question
     *
     * @param string $url
     *
     * @return array
     */
    public static function get($url)
    {
        return (array) FrontendModel::getContainer()->get('database')->getRecord(
            'SELECT i.*, m.url, c.title AS category_title, m2.url AS category_url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN faq_categories AS c ON i.category_id = c.id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE m.url = ? AND i.language = ? AND i.hidden = ?
             ORDER BY i.sequence',
            array((string) $url, FRONTEND_LANGUAGE, 'N')
        );
    }

    /**
     * Get all items in a category
     *
     * @param int   $categoryId
     * @param int   $limit
     * @param mixed $excludeIds
     *
     * @return array
     */
    public static function getAllForCategory($categoryId, $limit = null, $excludeIds = null)
    {
        $categoryId = (int) $categoryId;
        $limit = (int) $limit;
        $excludeIds = (empty($excludeIds) ? array(0) : (array) $excludeIds);

        // get items
        if ($limit != null) {
            $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
                'SELECT i.*, m.url
                 FROM faq_questions AS i
                 INNER JOIN meta AS m ON i.meta_id = m.id
                 WHERE i.category_id = ? AND i.language = ? AND i.hidden = ?
                 AND i.id NOT IN (' . implode(',', $excludeIds) . ')
             ORDER BY i.sequence
             LIMIT ?',
                array((int) $categoryId, FRONTEND_LANGUAGE, 'N', (int) $limit)
            );
        } else {
            $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
                'SELECT i.*, m.url
                 FROM faq_questions AS i
                 INNER JOIN meta AS m ON i.meta_id = m.id
                 WHERE i.category_id = ? AND i.language = ? AND i.hidden = ?
                 AND i.id NOT IN (' . implode(',', $excludeIds) . ')
             ORDER BY i.sequence',
                array((int) $categoryId, FRONTEND_LANGUAGE, 'N')
            );
        }

        // init var
        $link = FrontendNavigation::getURLForBlock('Faq', 'Detail');

        // build the item urls
        foreach ($items as &$item) {
            $item['full_url'] = $link . '/' . $item['url'];
        }

        return $items;
    }

    /**
     * Get all categories
     *
     * @return array
     */
    public static function getCategories()
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.*, m.url
             FROM faq_categories AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.language = ?
             ORDER BY i.sequence',
            array(FRONTEND_LANGUAGE)
        );

        // init var
        $link = FrontendNavigation::getURLForBlock('Faq', 'Category');

        // build the item url
        foreach ($items as &$item) {
            $item['full_url'] = $link . '/' . $item['url'];
        }

        return $items;
    }

    /**
     * Get a category
     *
     * @param string $url
     *
     * @return array
     */
    public static function getCategory($url)
    {
        return (array) FrontendModel::getContainer()->get('database')->getRecord(
            'SELECT i.*, m.url
             FROM faq_categories AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE m.url = ? AND i.language = ?
             ORDER BY i.sequence',
            array((string) $url, FRONTEND_LANGUAGE)
        );
    }

    /**
     * Get a category by id
     *
     * @param int $id
     *
     * @return array
     */
    public static function getCategoryById($id)
    {
        return (array) FrontendModel::getContainer()->get('database')->getRecord(
            'SELECT i.*, m.url
             FROM faq_categories AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.id = ? AND i.language = ?
             ORDER BY i.sequence',
            array((int) $id, FRONTEND_LANGUAGE)
        );
    }

    /**
     * Fetch the list of tags for a list of items
     *
     * @param array $ids
     *
     * @return array
     */
    public static function getForTags(array $ids)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.question AS title, m.url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON m.id = i.meta_id
             WHERE i.hidden = ? AND i.id IN (' . implode(',', $ids) . ')
             ORDER BY i.question',
            array('N')
        );

        if (!empty($items)) {
            $link = FrontendNavigation::getURLForBlock('Faq', 'Detail');

            // build the item urls
            foreach ($items as &$row) {
                $row['full_url'] = $link . '/' . $row['url'];
            }
        }

        return $items;
    }

    /**
     * Get the id of an item by the full URL of the current page.
     * Selects the proper part of the full URL to get the item's id from the database.
     *
     * @param FrontendURL $url
     *
     * @return int
     */
    public static function getIdForTags(FrontendURL $url)
    {
        $itemURL = (string) $url->getParameter(1);

        return self::get($itemURL);
    }

    /**
     * Get all items in a category
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getMostRead($limit)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.*, m.url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.num_views > 0 AND i.language = ? AND i.hidden = ?
             ORDER BY (i.num_usefull_yes + i.num_usefull_no) DESC
             LIMIT ?',
            array(FRONTEND_LANGUAGE, 'N', (int) $limit)
        );

        $link = FrontendNavigation::getURLForBlock('Faq', 'Detail');
        foreach ($items as &$item) {
            $item['full_url'] = $link . '/' . $item['url'];
        }

        return $items;
    }

    /**
     * Get the all questions for selected category
     *
     * @param int $id
     *
     * @return array
     */
    public static function getFaqsForCategory($id)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.category_id, i.question, i.hidden, i.sequence, m.url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.language = ? AND i.category_id = ?
             ORDER BY i.sequence ASC',
            array(FRONTEND_LANGUAGE, (int) $id)
        );

        $link = FrontendNavigation::getURLForBlock('Faq', 'Detail');

        foreach ($items as &$item) {
            $item['full_url'] = $link . '/' . $item['url'];
        }

        return $items;
    }

    /**
     * Get related items based on tags
     *
     * @param int $id
     * @param int $limit
     *
     * @return array
     */
    public static function getRelated($id, $limit = 5)
    {
        $relatedIDs = (array) FrontendTagsModel::getRelatedItemsByTags((int) $id, 'Faq', 'Faq');

        // there are no items, so return an empty array
        if (empty($relatedIDs)) {
            return array();
        }

        $link = FrontendNavigation::getURLForBlock('Faq', 'Detail');
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.question, m.url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             WHERE i.language = ? AND i.hidden = ? AND i.id IN(' . implode(',', $relatedIDs) . ')
             ORDER BY i.question
             LIMIT ?',
            array(FRONTEND_LANGUAGE, 'N', (int) $limit),
            'id'
        );

        foreach ($items as &$row) {
            $row['full_url'] = $link . '/' . $row['url'];
        }

        return $items;
    }

    /**
     * Increase the number of views for this item
     *
     * @param int $id
     *
     * @return array
     */
    public static function increaseViewCount($id)
    {
        FrontendModel::getContainer()->get('database')->execute(
            'UPDATE faq_questions SET num_views = num_views + 1 WHERE id = ?',
            array((int) $id)
        );
    }

    /**
     * Saves the feedback
     *
     * @param array $feedback
     */
    public static function saveFeedback(array $feedback)
    {
        $feedback['created_on'] = FrontendModel::getUTCDate();
        unset($feedback['sentOn']);

        FrontendModel::getContainer()->get('database')->insert('faq_feedback', $feedback);
    }

    /**
     * Parse the search results for this module
     *
     * Note: a module's search function should always:
     *        - accept an array of entry id's
     *        - return only the entries that are allowed to be displayed, with their array's index being the entry's id
     *
     *
     * @param array $ids
     *
     * @return array
     */
    public static function search(array $ids)
    {
        $items = (array) FrontendModel::getContainer()->get('database')->getRecords(
            'SELECT i.id, i.question AS title, i.answer AS text, m.url,
             c.title AS category_title, m2.url AS category_url
             FROM faq_questions AS i
             INNER JOIN meta AS m ON i.meta_id = m.id
             INNER JOIN faq_categories AS c ON c.id = i.category_id
             INNER JOIN meta AS m2 ON c.meta_id = m2.id
             WHERE i.hidden = ? AND i.language = ? AND i.id IN (' . implode(',', $ids) . ')',
            array('N', FRONTEND_LANGUAGE),
            'id'
        );

        // prepare items for search
        $detailUrl = FrontendNavigation::getURLForBlock('Faq', 'Detail');
        foreach ($items as &$item) {
            $item['full_url'] = $detailUrl . '/' . $item['url'];
        }

        return $items;
    }

    /**
     * Increase the number of views for this item
     *
     * @param int        $id
     * @param bool       $useful
     * @param mixed $previousFeedback
     *
     * @return array
     */
    public static function updateFeedback($id, $useful, $previousFeedback = null)
    {
        // feedback hasn't changed so don't update the counters
        if ($previousFeedback !== null && $useful == $previousFeedback) {
            return;
        }

        $db = FrontendModel::getContainer()->get('database');

        // update counter with current feedback (increase)
        if ($useful) {
            $db->execute(
                'UPDATE faq_questions
                 SET num_usefull_yes = num_usefull_yes + 1
                 WHERE id = ?',
                array((int) $id)
            );
        } else {
            $db->execute(
                'UPDATE faq_questions
                 SET num_usefull_no = num_usefull_no + 1
                 WHERE id = ?',
                array((int) $id)
            );
        }

        // update counter with previous feedback (decrease)
        if ($previousFeedback) {
            $db->execute(
                'UPDATE faq_questions
                 SET num_usefull_yes = num_usefull_yes - 1
                 WHERE id = ?',
                array((int) $id)
            );
        } elseif ($previousFeedback !== null) {
            $db->execute(
                'UPDATE faq_questions
                 SET num_usefull_no = num_usefull_no - 1
                 WHERE id = ?',
                array((int) $id)
            );
        }
    }
}
