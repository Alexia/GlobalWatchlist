<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Populate Maintenance
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
**/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class gwlPopulate extends Maintenance {
	/**
	 * In the event of a catastrophic Redis failure this script can be ran to populate all the backed up data on the master database back into Redis.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		$this->DB = wfGetDB(DB_MASTER);
		$this->redis = RedisCache::getClient('cache');

		/******************************/
		/* Lists                      */
		/******************************/
		$result = $this->DB->select(
			['gwl_watchlist'],
			['count(*) as items'],
			null,
			__METHOD__
		);
		$total = $result->fetchRow();

		for ($i = 0; $i <= $total['items']; $i += 100) {
			$result = $this->DB->select(
				['gwl_watchlist'],
				['*'],
				null,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 100
				]
			);

			while ($row = $result->fetchRow()) {
				if ($row['global_id'] < 1 || empty($row['site_key'])) {
					continue;
				}
				$this->redis->hSet('globalwatchlist:list:'.$row['global_id'], $row['site_key'], $row['list']);
			}
		}

		/******************************/
		/* Settings                   */
		/******************************/
		$result = $this->DB->select(
			['gwl_settings'],
			['count(*) as items'],
			null,
			__METHOD__
		);
		$total = $result->fetchRow();

		for ($i = 0; $i <= $total['items']; $i += 100) {
			$result = $this->DB->select(
				['gwl_settings'],
				['*'],
				null,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 100
				]
			);

			while ($row = $result->fetchRow()) {
				if ($row['global_id'] < 1 || empty($row['site_keys'])) {
					continue;
				}
				$this->redis->del($this->redisSitesKey);
				$siteKeys = unserialize($row['site_keys']);
				array_unshift($siteKeys, 'globalwatchlist:visibleSites:'.$row['global_id']);
				call_user_func_array([$this->redis, 'sAdd'], $siteKeys);
			}
		}
	}
}

$maintClass = 'gwlPopulate';
require_once(RUN_MAINTENANCE_IF_MAIN);
