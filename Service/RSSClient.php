<?php

/*
 * This file is part of the symfony-madrid package.
 * 
 * Short description   
 *
 * @author Daniel González <daniel.gonzalez@freelancemadrid.es>
 * @date Jun 19, 2012, 11:18:58 PM
 * @file RSSClient.php , UTF-8
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Desarrolla2\Bundle\RSSClientBundle\Service;

use Desarrolla2\Bundle\RSSClientBundle\Model\RSSNode;
use Desarrolla2\Bundle\RSSClientBundle\Model\RSSClientInterface;

class RSSClient implements RSSClientInterface
{

    /**
     * @var array 
     */
    protected $feeds = array();

    /**
     * @var array 
     */
    protected $nodes = array();

    /**
     * @var string 
     */

    const APCKEY = 'd2.client.rss.nodes';

    /**
     * @var string 
     */
    protected $apcHash = null;

    /**
     * Construnctor
     * 
     * @param string $channel
     * @param array $feeds
     */
    public function __construct($feeds = array(), $channel = 'default')
    {
        $this->setFeeds($feeds, $channel);
        return;
    }

    /**
     * Create Channel if not exist;
     * 
     * @param string $channel
     */
    protected function createChannel($channel = 'default')
    {
        if (!isset($this->feeds[$channel])) {
            $this->feeds[$channel] = array();
            return;
        }
        if (!is_array($this->feeds[$channel])) {
            $this->feeds[$channel] = array();
            return;
        }
        return;
    }

    /**
     * Generate a unique hash for apc
     * 
     * @param string $channel
     * @return string 
     */
    protected function getApcKey($channel = 'default')
    {
        if (!isset($this->apcHash[$channel])) {
            $this->apcHash[$channel] = self::APCKEY . '_' . md5(implode('|', $this->getFeeds($channel)));
        }
        return $this->apcHash[$channel];
    }

    /**
     * Retrieve feeds from a channel
     * 
     * @param string $channel
     * @return array feeds
     */
    public function getFeeds($channel = 'default')
    {
        $this->createChannel($channel);
        return $this->feeds[$channel];
    }

    /**
     * Clear feeds
     * 
     * @param string $channel
     */
    protected function clearFeeds($channel = 'default')
    {
        $this->feeds[$channel] = array();
        return;
    }

    /**
     * Set feed in a hacnnel
     * 
     * @param string $feed 
     * @param string $channel
     */
    public function setFeed($feed, $channel = 'default')
    {
        $this->clearFeeds($channel);
        $this->addFeed($feed, $channel);
        return;
    }

    /**
     * Set feeds in a channel
     * 
     * @param array $feeds
     * @param string $channel 
     */
    public function setFeeds($feeds, $channel = 'default')
    {
        $this->clearFeeds($channel);
        $this->addFeeds($feeds, $channel);
        return;
    }

    /**
     * Add feed to channel
     * 
     * @param string $feed 
     * @param string $channel
     */
    public function addFeed($feed, $channel = 'default')
    {
        $this->createChannel($channel);
        array_push($this->feeds[$channel], (string) $feed);
        return;
    }

    /**
     * Add feeds to channel
     *       
     * @param array $feeds 
     * @param string $channel
     */
    public function addFeeds($feeds, $channel = 'default')
    {
        $feeds = (array) $feeds;
        foreach ($feeds as $feed) {
            $this->addFeed($feed, $channel);
        }
        return;
    }

    /**
     * 
     * Retrieve the number of channels
     * 
     * @return int count $feeds
     */
    public function countChanels()
    {
        return count($this->feeds);
    }

    /**
     * 
     * Retrieve the number of feeds from a channels
     * 
     * @param string $channel
     * @return int count $feeds
     */
    public function countFeeds($channel = 'default')
    {
        $this->createChannel($channel);
        return count($this->feeds[$channel]);
    }

    /**
     * Add node
     * 
     * @param RSSNode $node
     * @param string $channel
     */
    protected function addNode(RSSNode $node, $channel = 'default')
    {
        if (!isset($this->nodes[$channel])) {
            $this->nodes[$channel] = array();
        }
        if (!is_array($this->nodes[$channel])) {
            $this->nodes[$channel] = array();
        }
        array_push($this->nodes[$channel], $node);
        return;
    }

    /**
     * Retrieve the number of nodes from a chanel
     * 
     * @param string $channel
     * @return int count $nodes
     */
    public function countNodes($channel = 'default')
    {
        if (!isset($this->nodes[$channel])) {
            $this->nodes[$channel] = array();
        }
        if (!is_array($this->nodes[$channel])) {
            $this->nodes[$channel] = array();
        }
        return count($this->nodes[$channel]);
    }

    /**
     * Retrieve nodes from a chanel
     * 
     * @param int $limit
     * @param string $channel
     * @return int $nodes
     */
    public function fetch($limit = 20, $channel = 'default')
    {
        $this->createChannel($channel);
        if ($nodes = $this->getCache($channel)) {
            $this->nodes[$channel] = $nodes;
        } else {
            foreach ($this->feeds[$channel] as $feed) {
                $feed = @file_get_contents($feed);
                if ($feed) {
                    $DOMDocument = new \DOMDocument();
                    $DOMDocument->strictErrorChecking = false;
                    if ($DOMDocument->loadXML($feed)) {
                        $nodes = $DOMDocument->getElementsByTagName('item');
                        foreach ($nodes as $node) {
                            try {
                                $this->addNode(
                                        new RSSNode(
                                                array(
                                                    'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                                                    'desc' => $node->getElementsByTagName('description')->item(0)->nodeValue,
                                                    'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
                                                    'date' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue
                                                )
                                        ), $channel
                                );
                            } catch (Exception $e) {
                                // ..  
                            }
                        }
                    }
                }
            }
            $this->setCache($channel);
        }
        $this->sort($channel);

        return $this->getNodes((int) $limit, $channel);
    }

    /**
     * Set APC cache 
     * 
     * @param string $channel
     */
    protected function setCache($channel = 'default')
    {
        if (extension_loaded('apc')) {
            if (function_exists('apc_exists')) {
                if (apc_exists($this->getApcKey($channel))) {
                    apc_store($this->getApcKey($channel), $this->nodes, 3600);
                }
            }
        }
        return;
    }

    /**
     * Retrieves from APC cache
     * 
     * @param string $channel
     * @return boolean 
     */
    protected function getCache($channel)
    {
        if (extension_loaded('apc')) {
            if (function_exists('apc_store')) {
                if (apc_exists($this->getApcKey($channel))) {
                    return apc_fetch($this->getApcKey($channel));
                }
            }
        }
        return false;
    }

    /**
     * Retrieves a $limit number of nodes
     * 
     * @param int $limit
     * @param string $channel
     * @return array $nodes
     */
    public function getNodes($limit = 20, $channel = 'default')
    {
        if (is_array($this->nodes[$channel])) {
            $limit = (int) $limit;
            $response = array();
            for ($i = 0; $i < $limit; $i++) {
                if (isset($this->nodes[$channel][$i])) {
                    array_push($response, $this->nodes[$channel][$i]);
                }
            }
            return $response;
        }
        // Exception ??
        return false;
    }

    /**
     * Sort by buuble method
     * 
     * @param string $channel
     */
    protected function sort($channel)
    {
        $countNodes = $this->countNodes($channel);
        for ($i = 1; $i < $countNodes; $i++) {
            for ($j = 0; $j < $countNodes - $i; $j++) {
                if ($this->nodes[$channel][$j]->getTimestamp() < $this->nodes[$channel][$j + 1]->getTimestamp()) {
                    $k = $this->nodes[$channel][$j + 1];
                    $this->nodes[$channel][$j + 1] = $this->nodes[$channel][$j];
                    $this->nodes[$channel][$j] = $k;
                }
            }
        }
        return;
    }

}