<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Mediawiki Settings
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		GlobalWatchlist
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

/******************************************/
/* Credits                                */
/******************************************/
$credits = array(
	'path'           => __FILE__,
	'name'           => 'GlobalWatchlist',
	'author'         => 'Alexia E. Smith',
	'descriptionmsg' => 'globalwatchlist_description',
	'version'        => '1.0'
);
$wgExtensionCredits['other'][] = $credits;


/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__;
define('GWL_EXT_DIR', __DIR__);

$wgExtensionMessagesFiles['GlobalWatchlistAlias']	= "{$extDir}/GlobalWatchlist.alias.php";
$wgMessagesDirs['GlobalWatchlist']					= "{$extDir}/i18n";

$wgAutoloadClasses['GlobalWatchlistHooks']			= "{$extDir}/GlobalWatchlist.hooks.php";
$wgAutoloadClasses['SpecialGlobalWatchlist']		= "{$extDir}/specials/SpecialGlobalWatchlist.php";
$wgAutoloadClasses['globalWatchlist']				= "{$extDir}/classes/globalWatchlist.php";
$wgAutoloadClasses['globalRevisionList']			= "{$extDir}/classes/globalRevisionList.php";
$wgAutoloadClasses['gwlSync']						= "{$extDir}/classes/gwlSync.php";
$wgAutoloadClasses['grlSync']						= "{$extDir}/classes/grlSync.php";

$wgAutoloadClasses['TemplateGlobalWatchlist']		= "{$extDir}/templates/TemplateGlobalWatchlist.php";

$wgSpecialPages['GlobalWatchlist']					= 'SpecialGlobalWatchlist';

$wgHooks['WatchArticleComplete'][]					= 'GlobalWatchlistHooks::onWatchArticleComplete';
$wgHooks['UnwatchArticleComplete'][]				= 'GlobalWatchlistHooks::onUnwatchArticleComplete';
$wgHooks['PageContentSaveComplete'][]				= 'GlobalWatchlistHooks::onPageContentSaveComplete';
$wgHooks['PersonalUrls'][]							= 'GlobalWatchlistHooks::onPersonalUrls';
if (MASTER_WIKI === true || $wgSiteKey == 'master') {
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'GlobalWatchlistHooks::onLoadExtensionSchemaUpdates';
}

$extSyncServices[] = 'gwlSync';
$extSyncServices[] = 'grlSync';

$wgResourceModules['ext.globalwatchlist'] = [
	'localBasePath' => $extDir,
	'remoteExtPath' => 'GlobalWatchlist',
	'styles'        => ['css/globalwatchlist.css'],
	'scripts'       => ['js/globalwatchlist.js'],
];
