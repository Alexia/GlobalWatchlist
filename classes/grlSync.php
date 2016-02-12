<?php
/**
 * GlobalWatchlist
 * Global Revision List Synchronizer Service
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
**/

class grlSync extends SyncService\Job {
	/**
	 * Cleans up the revision list in Redis to remove old entries.
	 *
	 * @access	public
	 * @param	array	Named arguments passed by the command that queued this job.
	 * - site_key	The MD5 site key for this global watch list entry.
	 * @return	boolean	Success
	 */
	public function execute($args = []) {
		$siteKey	= $args['site_key'];

		if (strlen($siteKey) != 32 && $siteKey != 'master') {
			return false;
		}

		try {
			$grl = globalRevisionList::newFromSite($siteKey);

			if ($grl !== false) {
				$grl->getList();
				$grl->save();
			}
			return true;
		} catch (Exception $e) {
			self::queue($args);
			return false;
		}
	}
}
