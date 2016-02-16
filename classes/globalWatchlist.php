<?php
/**
 * GlobalWatchlist
 * globalWatchlist Class
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

class globalWatchlist {
	/**
	 * This wiki's site key.
	 *
	 * @var		string
	 */
	private $siteKey;

	/**
	 * Valid Mediawiki User object.
	 *
	 * @var		object
	 */
	private $user;

	/**
	 * Global User ID
	 *
	 * @var		integer
	 */
	private $globalId;

	/**
	 * List of globally watched items.
	 *
	 * @var		array
	 */
	private $list;

	/**
	 * List of visible sites on the global watch list.
	 *
	 * @var		mixed[array|null]
	 */
	private $visibleSites = null;

	/**
	 * PageCount int for how many pages exist in a watchlist.
	 *
	 * @var		int
	 */
	private $pageCount = 0;

	/**
	 * Redis key for the user's list hash set.
	 *
	 * @var		string
	 */
	private $redisListKey;

	/**
	 * Redis key for the sites settings set.
	 *
	 * @var		string
	 */
	private $redisSitesKey;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	Configuration
	 * @return	void
	 */
	public function __construct() {
		global $wgSiteKey;

		$this->siteKey = $wgSiteKey;

		$this->redis = RedisCache::getClient('cache');
	}

	/**
	 * Create a new object instance based on an User object.
	 *
	 * @access	public
	 * @param	object	Mediawiki User Object
	 * @return	mixed	bool|user object
	 */
	static public function newFromUser(User $user) {
		$gwl = new self();
		if (!$gwl->setUser($user)) {
			return false;
		}

		return $gwl;
	}

	/**
	 * Add a local article to the global watch list.
	 *
	 * @access	public
	 * @param	object	WikiPage object.
	 * @return	boolean Success
	 */
	public function addArticle(WikiPage $article) {
		global $wgSitename, $wgMetaNamespace, $wgServer, $wgScriptPath;
		if (!$this->siteKey) {
			return false;
		}

		$articleTitle = $article->getTitle();

		if ($articleTitle->getArticleID() == 0) {
			return false;
		}

		$data = [
			'user'		=> [
				'mName'				=> $this->user->getName(),
				'global_id'			=> $this->globalId
			],
			'article'	=> [
				'mTextform'			=> $articleTitle->getText(),
				'mNamespace'		=> $articleTitle->getNamespace(),
				'mArticleID'		=> $articleTitle->getArticleID(),
				'mLatestID'			=> $articleTitle->getLatestRevID()
			],
			'site'		=> [
				'wiki_name'			=> $wgSitename,
				'wiki_meta_name'	=> $wgMetaNamespace,
				'wiki_domain'		=> $wgServer,
				'url_prefix'		=> wfExpandUrl($wgServer.$wgScriptPath)
			],
		];

		$list = $this->getList();

		$list[$this->siteKey][$data['article']['mArticleID']] = $data;

		return $this->setList($list);
	}

	/**
	 * Remove a local article to the global watch list.
	 *
	 * @access	public
	 * @param	object	WikiPage object.
	 * @return	boolean Success
	 */
	public function removeArticle(WikiPage $article) {
		if (!$this->siteKey) {
			return false;
		}

		$list = $this->getList();

		unset($list[$this->siteKey][$article->mTitle->mArticleID]);

		return $this->setList($list);
	}

	/**
	 * Save the list into Redis.
	 *
	 * @access	public
	 * @return	boolean	True on success, False is Redis down.
	 */
	public function save() {
		if ($this->redis === false) {
			return false;
		}
		try {
			if (is_array($this->list) && count($this->list)) {
				$args = [];
				foreach ($this->list as $siteKey => $data) {
					if (empty($this->list[$siteKey])) {
						//Clean up empty wiki watch lists.
						unset($this->list[$siteKey]);
						$this->redis->hDel($this->redisListKey, $siteKey);
						gwlSync::queue(['site_key' => $siteKey, 'global_id' => $this->globalId, 'list' => null, 'type' => 'list']);
						continue;
					}
					$args[$siteKey] = serialize($data);
					gwlSync::queue(['site_key' => $siteKey, 'global_id' => $this->globalId, 'list' => serialize($data), 'type' => 'list']);
				}
				$this->redis->hMSet($this->redisListKey, $args);
			}
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
		return true;
	}

	/**
	 * Returns the global watch list.
	 *
	 * @access	public
	 * @return	array	Global Watch List
	 */
	public function getList() {
		$this->getVisibleSites();

		try {
			$list = $this->redis->hGetAll($this->redisListKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return [];
		}
		if (is_array($list) && count($list)) {
			foreach ($list as $siteKey => $data) {
				//Make sure visible sites are respected, but allow master through regardless.
				if (!in_array($siteKey, $this->visibleSites) && $siteKey != 'master') {
					unset($list[$siteKey]);
					continue;
				}
				$list[$siteKey] = unserialize($data);
			}
			$this->list = $list;
			return $this->list;
		}

		return [];
	}

	/**
	 * Sets the list of globally watched articles.
	 *
	 * @access	private
	 * @return	boolean Returns false on error.
	 */
	private function setList($list) {
		$this->list = $list;

		return true;
	}

	/**
	 * Get what sites are visible on the global watch list.
	 *
	 * @access	public
	 * @return	mixed	Array of visible site keys or false on error.
	 */
	public function getVisibleSites() {
		if ($this->getUser() === false) {
			return false;
		}

		if ($this->visibleSites === null) {
			$_sites = $this->redis->sMembers($this->redisSitesKey);
			if (is_array($_sites)) {
				$this->visibleSites = $_sites;
			} else {
				$this->visibleSites = [];
			}
		}

		natcasesort($this->visibleSites);

		return $this->visibleSites;
	}

	/**
	 * Set what sites are visible on the global watch list.
	 *
	 * @access	public
	 * @param	array	[Optional] Site Keys to make visible.  Calling this function with no parameters will make all sites invisible.
	 * @return	boolean Success
	 */
	public function setVisibleSites($siteKeys = []) {
		if ($this->getUser() === false) {
			return false;
		}

		foreach ($siteKeys as $index => $siteKey) {
			if (strlen($siteKey) != 32) {
				unset($siteKeys[$index]);
			}
		}
		$this->visibleSites = $siteKeys;
		array_unshift($siteKeys, $this->redisSitesKey);
		$this->redis->del($this->redisSitesKey);
		call_user_func_array([$this->redis, 'sAdd'], $siteKeys);
		gwlSync::queue(['global_id' => $this->globalId, 'site_keys' => serialize($this->visibleSites), 'type' => 'settings']);

		return true;
	}

	/**
	 * Return the User object or false if invalid.
	 *
	 * @access	public
	 * @return	mixed	User object or False
	 */
	public function getUser() {
		return ($this->user instanceOf User ? $this->user : false);
	}

	/**
	 * Set the User object.
	 *
	 * @access	public
	 * @param	object	Mediawiki User
	 * @return	boolean Success
	 */
	public function setUser(User $user) {
		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId) {
			return false;
		}

		$this->user = $user;
		$this->globalId = $globalId;
		$this->redisListKey = 'globalwatchlist:list:'.$this->globalId;
		$this->redisSitesKey = 'globalwatchlist:visibleSites:'.$this->globalId;

		return true;
	}


	/**
	 * Get count of pages in a User's Global Watchlist
	 *
	 * @access	public
	 * @param	array	Watchlist
	 * @param	array	List of Visible Sites
	 * @return	int		Count of pages
	 */
	public function getCount($list, $visibleSites) {
		if (is_array($list) && count($list)) {
			foreach ($list as $siteKey => $data) {
				if (array_key_exists($siteKey, $visibleSites)) {
					$this->pageCount += count($data);
				}
			}
			return $this->pageCount;
		}

		return 0;
	}
}
