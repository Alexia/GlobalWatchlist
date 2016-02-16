<?php
/**
 * GlobalWatchlist
 * globalRevisionList Class
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

class globalRevisionList {
	/**
	 * This wiki's site key.
	 *
	 * @var		string
	 */
	private $siteKey;

	/**
	 * List of globally revisioned items.
	 *
	 * @var		array
	 */
	private $list = [];

	/**
	 * Redis key for the revision hash set.
	 *
	 * @var		string
	 */
	private $redisKey;

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
	 * Create a new object instance from a wiki site key.
	 *
	 * @access	public
	 * @param	string	Wiki Site Key
	 * @return	mixed	bool|Site Object
	 */
	static public function newFromSite($siteKey) {
		if (strlen($siteKey) != 32 && $siteKey != 'master') {
			return false;
		}

		$grl = new self();
		$grl->setSite($siteKey);

		return $grl;
	}

	/**
	 * Add a local revision to the global revision list.
	 *
	 * @access	public
	 * @param	Revision  WikiPage object.
	 * @param	boolean   [Optional] Whether to immediately add to Redis instead of relying on save() to reprocess the whole list.
	 * @return	boolean   Success
	 */
	public function addRevision($revision, $immediate = false) {
		if (!$revision instanceOf Revision || $revision->getPage() < 1 || !$this->siteKey) {
			return false;
		}

		/*$data = [
			'mId'			=> $revision->mId,
			'mPage'			=> $revision->mPage,
			'mUserText'		=> $revision->mUserText,
			'mMinorEdit'	=> $revision->mMinorEdit,
			'mTimestamp'	=> $revision->mTimestamp,
			'mParentId'		=> $revision->mParentId,
			'mComment'		=> $revision->mComment,
			'mTimestamp'	=> $revision->mTimestamp,
			'mTimestamp'	=> $revision->mTimestamp,
		];*/

		$this->list[$revision->getPage()] = $revision;
		if ($immediate === true) {
			// make sure prefixed title text is cached
			$revision->getTitle()->getPrefixedText();
			$this->redis->hSet($this->redisKey, $revision->getPage(), serialize($revision));
		}

		return true;
	}

	/**
	 * Remove a local revision to the global revision list.
	 *
	 * @access	public
	 * @param	object	WikiPage object.
	 * @param	boolean	[Optional] Whether to immediately remove from Redis instead of relying on save() to reprocess the whole list.
	 * @return	boolean	Success
	 */
	public function removeRevision($revision, $immediate = false) {
		if (!$revision instanceOf Revision || $revision->getPage() < 1 || !$this->siteKey) {
			return false;
		}

		unset($this->list[$revision->getPage()]);
		if ($immediate === true) {
			$this->redis->hDel($this->redisKey, $revision->getPage());
		}

		return true;
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
		if (is_array($this->list) && count($this->list)) {
			$args = [];
			$oldRevisionTimestamp = time() - 604800;
			foreach ($this->list as $articleId => $revision) {
				if (!empty($revision)) {
					$timestamp = wfTimestamp(TS_UNIX, $revision->getTimestamp());
					if ($timestamp <= $oldRevisionTimestamp) {
						//Clean up empty wiki revision lists.
						unset($this->list[$articleId]);
						$this->redis->hDel($this->redisKey, $articleId);
						continue;
					}
					//Make sure prefixed title text is cached.
					$revision->getTitle()->getPrefixedText();
					$args[$articleId] = serialize($revision);
				} else {
					unset($this->list[$articleId]);
					$this->redis->hDel($this->redisKey, $articleId);
				}
			}
			$this->redis->hMSet($this->redisKey, $args);
		}
		return true;
	}

	/**
	 * Returns the global revision list.
	 *
	 * @access	public
	 * @return	array	Global Watch List
	 */
	public function getList() {
		$list = $this->redis->hGetAll($this->redisKey);
		if (is_array($list) && count($list)) {
			foreach ($list as $articleId => $revision) {
				$list[$articleId] = unserialize($revision);
			}

			//Merge with local changes that were done before calling getList().
			if (is_array($this->list) && count($this->list)) {
				$this->list = array_merge($list, $this->list);
			} else {
				$this->list = $list;
			}
			return $this->list;
		} else {
			return [];
		}
	}

	/**
	 * Sets the list of globally revisioned revisions.
	 *
	 * @access	private
	 * @return	boolean	Returns false on error.
	 */
	private function setList($list) {
		$this->list = $list;

		return true;
	}

	/**
	 * Returns the site key or false if invalid.
	 *
	 * @access	public
	 * @return	mixed	Site Key or False
	 */
	public function getSite() {
		return (strlen($this->siteKey) == 32 ? $this->siteKey : false);
	}

	/**
	 * Set the site key.
	 *
	 * @access	public
	 * @param	string	Wiki Site Key
	 * @return	boolean	Success
	 */
	public function setSite($siteKey) {
		if (strlen($siteKey) == 32 || $siteKey == 'master') {
			$this->redisKey = 'globalrevisionlist:'.$siteKey;
			$this->siteKey = $siteKey;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Queue an update to the revision list on a daily basis.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function queueUpdate() {
		if (!$this->siteKey) {
			return false;
		}
		if ($this->redis->ttl('globalrevisionlist:updateTimer:'.$this->siteKey) < 1) {
			grlSync::queue(['site_key' => $this->siteKey]);
			$this->redis->set('globalrevisionlist:updateTimer:'.$this->siteKey, 'Bananas');
			$this->redis->expire('globalrevisionlist:updateTimer:'.$this->siteKey, 86400);

			return true;
		}
		return false;
	}
}
