<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Hooks
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

class GlobalWatchlistHooks {
	/**
	 * Hooks Initialized
	 *
	 * @var		boolean
	 */
	private static $initialized = false;

	/**
	 * Mediawiki Database Object
	 *
	 * @var		object
	 */
	static private $DB;

	/**
	 * Redis Storage
	 *
	 * @var		object
	 */
	static private $redis = false;

	/**
	 * This wiki's site key.
	 *
	 * @var		string
	 */
	static private $siteKey;

	/**
	 * Initiates some needed classes.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function init() {
		if (!self::$initialized) {
			global $wgSiteKey;

			self::$siteKey = $wgSiteKey;

			self::$DB = wfGetDB(DB_MASTER);

			self::$redis = RedisCache::getClient('cache');

			self::$initialized = true;
		}
	}

	/**
	 * Handles adding watched articles to the global watch list.
	 *
	 * @access	public
	 * @param	object	User object of the user who watched this page.
	 * @param	object	WikiPage object of the watched page.
	 * @return	boolean	True
	 */
	static public function onWatchArticleComplete(User $user, WikiPage $article) {
		if (!$article->mDataLoaded) {
			$article->loadPageData();
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId || $article->mTitle->mArticleID < 1) {
			return true;
		}

		$gwl = globalWatchlist::newFromUser($user);

		if ($gwl !== false && $gwl->addArticle($article) === true) {
			//If adding the specific page the user requested to watch was successful then we need to add the associated namespace page.
			$title = Title::newFromText($article->mTitle->getText(), MWNamespace::getAssociated($article->mTitle->mNamespace));
			$associated = new WikiPage($title);
			if (!$associated->mDataLoaded) {
				$associated->loadPageData();
			}

			$gwl->addArticle($associated);

			//Save since at least the requested page to watch was successful lets save it.  If the $associated page is not added successfully we do not want it to stop the process.
			$success = $gwl->save();
		}

		return true;
	}

	/**
	 * Handles removed watched articles to the global watch list.
	 *
	 * @access	public
	 * @param	object	User object of the user who unwatched this page.
	 * @param	object	Article object of the unwatched page.
	 * @return	boolean	True
	 */
	static public function onUnwatchArticleComplete(User $user, WikiPage $article) {
		if (!$article->mDataLoaded) {
			$article->loadPageData();
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId || $article->mTitle->mArticleID < 1) {
			return true;
		}

		//The newFromUser function will check if the user is valid.  False will be return if not.
		$gwl = globalWatchlist::newFromUser($user);

		if ($gwl !== false && $gwl->removeArticle($article) === true) {
			//If removing the specific page the user requested to unwatch was successful then we need to remove the associated namespace page.
			$title = Title::newFromText($article->mTitle->getText(), MWNamespace::getAssociated($article->mTitle->mNamespace));
			$associated = new WikiPage($title);
			if (!$associated->mDataLoaded) {
				$associated->loadPageData();
			}

			$gwl->removeArticle($associated);

			//Save since at least the requested page to unwatch was successful lets save it.  If the $associated page is not removed successfully we do not want it to stop the process.
			$success = $gwl->save();
		}

		return true;
	}

	/**
	 * Handle dispatcher watch list updates when an article is saved.
	 *
	 * @access	public
	 * @param	object	$article: WikiPage modified
	 * @param	object	$user: User performing the modification
	 * @param	object	$content: MW 1.19, Raw Text, MW 1.21 New content, as a Content object
	 * @param	string	$summary: Edit summary/comment
	 * @param	boolean	$isMinor: Whether or not the edit was marked as minor
	 * @param	boolean	$isWatch: (No longer used)
	 * @param	object	$section: (No longer used)
	 * @param	integer	$flags: Flags passed to WikiPage::doEditContent()
	 * @param	mixed	$revision: New Revision of the article
	 * @param	object	$status: Status object about to be returned by doEditContent()
	 * @param	integer	$baseRevId: the rev ID (or false) this edit was based on
	 * @return	boolean True
	 */
	static public function onPageContentSaveComplete($article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId) {
		if ($revision instanceOf Revision) {
			self::init();

			$results = self::$DB->select(
				['watchlist'],
				['wl_title'],
				["wl_title = '".self::$DB->strencode($revision->getTitle()->mDbkeyform)."'"],
				__METHOD__
			);

			if (!$results) {
				return true;
			}

			$grl = globalRevisionList::newFromSite(self::$siteKey);

			if ($grl !== false) {
				$grl->addRevision($revision, true);
				$grl->queueUpdate();
			}
		}

		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param	object	[Optional] DatabaseUpdater Object
	 * @return	boolean	true
	 */
	static public function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater = null) {
		$extDir = __DIR__;

		$updater->addExtensionUpdate(['addTable', 'gwl_watchlist', "{$extDir}/install/sql/globalwatchlist_table_gwl_watchlist.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'gwl_settings', "{$extDir}/install/sql/globalwatchlist_table_gwl_settings.sql", true]);

		return true;
	}

	/**
	 * Insert global watchlist page link into the personal URLs.
	 *
	 * @access	public
	 * @param	array	Peronsal URLs array.
	 * @param	object	Title object for the current page.
	 * @param	object	SkinTemplate instance that is setting up personal urls
	 * @return	boolean True
	 */
	static public function onPersonalUrls(array &$personalUrls, Title $title, SkinTemplate $skin) {
		$URL = Skin::makeSpecialUrl('GlobalWatchlist');
		if (!$skin->getUser()->isAnon()) {
			$globalwatchlist = [
				'globalwatchlist'	=> [
						'text'		=> wfMessage('globalwatchlist')->text(),
						'href'		=> $URL,
						'active'	=> true
				]
			];

			Curse::array_insert_before_key($personalUrls, 'watchlist', $globalwatchlist);
		}

		return true;
	}
}
