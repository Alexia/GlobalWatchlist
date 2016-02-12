<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Synchronizer Service
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
**/

class gwlSync extends SyncService\Job {
	/**
	 * Saves/Backs Up global watch list data into the master database.
	 *
	 * @access	public
	 * @param	array	Named arguments passed by the command that queued this job.
	 * - global_id	Integer Global User ID
	 * - site_key	The MD5 site key for this global watch list entry.
	 * - list		Serialized list of watched articles.
	 * @return	boolean	Success
	 */
	public function execute($args = []) {
		$globalId	= intval($args['global_id']);
		$siteKey	= $args['site_key'];
		$siteKeys	= $args['site_keys'];
		$list		= $args['list'];
		$type		= $args['type'];

		if ($globalId < 1 || !in_array($type, ['list', 'settings'])) {
			return false;
		}

		try {
			switch ($type) {
				case 'list':
					$where = [
						'global_id'	=> $globalId,
						'site_key'	=> $siteKey
					];
					if (strlen($siteKey) != 32 && $siteKey != 'master') {
						return false;
					}
					if ($list === null) {
						//Nuke this entry from the database if it exists.
						$success = $this->DB->delete(
							'gwl_watchlist',
							$where,
							__METHOD__
						);
					} else {
						//Insert or Update this entry.
						$result = $this->DB->select(
							['gwl_watchlist'],
							['wid'],
							$where,
							__METHOD__
						);
						$exists = $result->fetchRow();

						$data = [
							'global_id'	=> $globalId,
							'site_key'	=> $siteKey,
							'list'		=> $list
						];
						if ($exists['wid'] > 0) {
							$success = $this->DB->update(
								'gwl_watchlist',
								$data,
								['wid' => $exists['wid']],
								__METHOD__
							);
						} else {
							$success = $this->DB->insert(
								'gwl_watchlist',
								$data,
								__METHOD__
							);
						}
					}
					break;
				case 'settings':
					$where = ['global_id' => $globalId];
					$result = $this->DB->select(
						['gwl_settings'],
						['sid'],
						$where,
						__METHOD__
					);
					$exists = $result->fetchRow();

					$data = [
						'global_id'	=> $globalId,
						'site_keys'	=> $siteKeys,
					];
					if ($exists['sid'] > 0) {
						$success = $this->DB->update(
							'gwl_settings',
							$data,
							['sid' => $exists['sid']],
							__METHOD__
						);
					} else {
						$success = $this->DB->insert(
							'gwl_settings',
							$data,
							__METHOD__
						);
					}
					break;
				default:
					$success = false;
					break;
			}
			return ($success === false ? false : true);
		} catch (Exception $e) {
			self::queue($args);
			return false;
		}
	}
}
