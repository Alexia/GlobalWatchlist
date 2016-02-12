<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

class SpecialGlobalWatchlist extends Curse\SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('GlobalWatchlist');
		$this->language		= $this->getLanguage();
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		if ($this->wgUser->isAnon()) {
			$this->output->showErrorPage('globalwatchlist_error', 'error_gwl_logged_out');
			return;
		}

		$this->redis = RedisCache::getClient('cache');
		$this->templateGlobalWatchlist = new TemplateGlobalWatchlist;

		$this->output->addModules('ext.globalwatchlist');

		$this->setHeaders();

		switch ($subpage) {
			default:
			case 'globalwatchlist':
				$this->globalWatchlist();
				break;
			case 'settings':
				$this->globalWatchlistSettings();
				break;
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Global Watch List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function globalWatchlist() {

		$gwl = globalWatchlist::newFromUser($this->wgUser);

		# Spit out some control panel links
		$filters = array(
			'hideMinor' 	=> 'rcshowhideminor',
			'hideBots' 		=> 'rcshowhidebots',
			'hideAnons' 	=> 'rcshowhideanons',
			'hideLiu' 		=> 'rcshowhideliu',
			'hideOwn' 		=> 'rcshowhidemine',
		);

		foreach ($filters as $key => $message) {
			$filterOptions[$key] = $this->wgRequest->getInt($key);
		}

		$showHideLinks = [];
		foreach ($filters as $key => $message) {
			$showHideLinks[] = $this->getShowHideLink($filterOptions, $message, $key, $filterOptions[$key]);
		}

		$secondsFilter = 0;
		if ($this->wgRequest->getInt('hours') > 0) {
			$secondsFilter = $this->wgRequest->getInt('hours') * 60 * 60;
		} elseif ($this->wgRequest->getInt('days') > 0) {
			$secondsFilter = $this->wgRequest->getInt('days') * 24 * 60 * 60;
		}

		$visibleSites = $gwl->getVisibleSites();
		if (is_array($visibleSites) && count($visibleSites)) {
			foreach ($visibleSites as $vsIndex => $siteKey) {
				try {
					$_site = $this->redis->hGetAll('dynamicsettings:siteInfo:'.$siteKey);
				} catch (RedisException $e) {
					throw new MWException(__METHOD__.": Caught RedisException - ".$e->getMessage());
				}
				if (is_array($_site) && count($_site)) {
					foreach ($_site as $index => $data) {
						$_site[$index] = unserialize($data);
					}
					unset($visibleSites[$vsIndex]);
					$visibleSites[$siteKey] = $_site['wiki_name']." (".strtoupper($_site['wiki_language']).")";
				} else {
					unset($visibleSites[$vsIndex]);
					$visibleSites[$siteKey] = $siteKey;
				}
			}
		}

		$watchList = $gwl->getList();
		$pageCount = $gwl->getCount($watchList, $visibleSites);

		$this->output->setPageTitle(wfMessage('globalwatchlist')->escaped());
		$this->content = $this->templateGlobalWatchlist->globalWatchlist($watchList, $pageCount, $this->getCutOffLinks(), $showHideLinks, $filterOptions, $secondsFilter, $visibleSites, $this);
	}

	/**
	 * Global Watch List Settings
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function globalWatchlistSettings() {
		try {
			$siteKeys = $this->redis->sMembers('dynamicsettings:siteHashes');
		} catch (RedisException $e) {
			throw new MWException(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
		$return = $this->globalWatchlistSettingsSave($siteKeys);

		if (is_array($siteKeys) && count($siteKeys)) {
			foreach ($siteKeys as $siteKey) {
				try {
					$_site = $this->redis->hGetAll('dynamicsettings:siteInfo:'.$siteKey);
				} catch (RedisException $e) {
					throw new MWException(__METHOD__.": Caught RedisException - ".$e->getMessage());
				}
				if (is_array($_site) && count($_site)) {
					foreach ($_site as $index => $data) {
						$_site[$index] = unserialize($data);
					}
					$_site['md5_key'] = $siteKey;
					$sites[$_site['md5_key']] = $_site;
				}
			}
		}

		$sites = $this->sortByKeyValue($sites, 'wiki_name');

		$this->output->setPageTitle(wfMessage('globalwatchlistsettings')->escaped());
		$this->content = $this->templateGlobalWatchlist->globalWatchlistSettings($sites, $return['visibleSites'], $return['success']);
	}

	/**
	 * Saves the form submit for globalWatchlistSettings.
	 *
	 * @access	public
	 * @param	array	Valid site keys.
	 * @return	array	List of visible site keys.
	 */
	public function globalWatchlistSettingsSave($siteKeys = []) {
		$gwl = globalWatchlist::newFromUser($this->wgUser);
		$success = false;
		if ($this->wgRequest->wasPosted()) {
			$sites = $this->wgRequest->getArray('sites');
			foreach ($sites as $key => $siteKey) {
				if (!in_array($siteKey, $siteKeys)) {
					unset($sites[$key]);
				}
			}
			$gwl->setVisibleSites($sites);
			$success = true;
		}
		return ['visibleSites' => $gwl->getVisibleSites(), 'success' => $success];
	}

	/**
	 * Creates a link to the GWL page to filter by time.
	 *
	 * @access	protected
	 * @param	mixed	The numeric amount to display or text to use.
	 * @param	string	String unit to use as part of the URL.
	 * @return	string	HTML link.
	 */
	protected function getTimePeriodLink($amount = null, $unit = null) {
		if (!empty($amount) && !empty($unit)) {
			$options[$unit] = ($amount);
		}

		return Linker::linkKnown(
			$this->getTitle(),
			$amount,
			[],
			$options
		);
	}

	/**
	 * Generates the list of cut off links for the number of items to display.
	 *
	 * @access	protected
	 * @return string	HTML cut off list.
	 */
	protected function getCutOffLinks() {
		$hours = [1, 2, 6, 12];
		$days = [1, 3, 7];

		foreach ($hours as $key => $hour) {
			$hours[$key] = $this->getTimePeriodLink($hour, 'hours');
		}

		foreach ($days as $key => $day) {
			$days[$key] = $this->getTimePeriodLink($day, 'days');
		}
		return $this->msg('wlshowlast')->rawParams(
			$this->getLanguage()->pipeList($hours),
			$this->getLanguage()->pipeList($days),
			''
		)->parse();
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	array	Additional parameters passed through the URL.
	 * @param	string	Localization message to use.
	 * @param	string	Parameter in the URL.
	 * @param	integer	Value to display for this option, 0 or 1.
	 * @return	string	HTML
	 */
	protected function getShowHideLink($options, $message, $name, $value) {
		$label = wfMessage($value == 0 ? 'hide' : 'show')->escaped();
		$options[$name] = ($value == 0 ? 1 : 0);

		foreach ($options as $key => $value) {
			if ($value == 0) {
				unset($options[$key]);
			}
		}

		return wfMessage($message)->rawParams(Linker::linkKnown($this->getTitle(), $label, [], $options))->escaped();
	}

	/**
	 * Sort a one deep multidimensional array by the values of a specified key.
	 *
	 * @access	public
	 * @param	array	Array to sort
	 * @param	string	Subarray key to sort by.
	 * @return	array	Sorted Array
	 */
	public function sortByKeyValue($array = array(), $sortKey, $sortOption = 'natcasesort') {
		$sorter = array();
		foreach ($array as $key => $info) {
			$sorter[$key] = $info[$sortKey];
		}

		if ($sortOption == 'asort') {
			asort($sorter);
		} else {
			natcasesort($sorter);
		}

		$sortedArray = array();
		foreach ($sorter as $key => $value) {
			$sortedArray[$key] = $array[$key];
		}
		return $sortedArray;
	}

	/**
	 * Sort a one deep multidimensional array by the values of a specified key.
	 *
	 * @access	public
	 * @param	array	Array to search
	 * @param	array	Subarray keys to search by.
	 * @return	array	Array of search results.
	 */
	public function searchByKeyValue($array = array(), $searchKey = array(), $searchTerm = '') {
		$searchTerm = mb_strtolower($searchTerm, 'UTF-8');
		$found = array();

		foreach ($array as $key => $info) {
			foreach ($searchKey as $sKey) {
				if (is_array($info[$sKey])) {
					$_temp = mb_strtolower(implode(',', $info[$sKey]), 'UTF-8');
				} else {
					$_temp = mb_strtolower($info[$sKey], 'UTF-8');
				}
				if (strpos($_temp, $searchTerm) !== false) {
					$found[$key] = $info;
				}
			}
		}

		return $found;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'changes';
	}
}
