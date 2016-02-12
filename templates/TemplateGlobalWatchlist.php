<?php
/**
 * GlobalWatchlist
 * GlobalWatchlist Template
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GPLv3
 * @package		Achievements
 * @link		https://github.com/HydraWiki/GlobalWatchlist
 *
 **/

class TemplateGlobalWatchlist {
	/**
	 * Achievements URL
	 *
	 * @var		string
	 */
	private $achievementsURL;

	/**
	 * Main Constructer
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgServer, $wgScriptPath, $wgUser;

		$achievementsPage		= Title::newFromText('Special:Achievements');
		$this->achievementsURL	= $achievementsPage->getFullURL();

		$this->urlPrefix = wfExpandUrl($wgServer.$wgScriptPath);
		$this->wgUser = $wgUser;
	}

	/**
	 * Achievement List
	 *
	 * @access	public
	 * @param	array   Array of watch lists for the user.
	 * @param	string  Cut Off Link HTML
	 * @param	array   Array of HTML links to hide or show items.
	 * @param	array   Filter options in reverse boolean. True or 1 = Hide.
	 * @param	integer Seconds into the past to allow.
	 * @param	array   Information of visible site keys.
	 * @param	object  SpecialGobalWatchList special page.
	 * @return	string  Built HTML
	 */
	public function globalWatchlist($globalWatchlist, $pageCount, $cutOffLinks, $showHideLinks, $filterOptions, $secondsFilter, $visibleSites, $specialPage) {
		global $wgShowUpdatedMarker;

		$settingsPage	= Title::newFromText('Special:GlobalWatchlist/settings');
		$settingsURL	= $settingsPage->getFullURL();
		if ($secondsFilter == 0) {
			$timeFilter = 7 * 24;
		} else {
			$timeFilter = $secondsFilter / 3600;
		}

		$HTML = "
				<div id='contentSub'>".wfMessage('gwl_for')->escaped()." {$specialPage->wgUser->getName()} <span class='mw-watchlist-toollinks'>(<a title='{$settingsPage->getText()}' href='{$settingsURL}'>".wfMessage('gwl_edit_settings')->escaped()."</a>)</span></div>
				<p>".wfMessage('watchlist-details', $pageCount)->parse()."</p>
				<form action='{$this->urlPrefix}/Special:GlobalWatchlist' id='mw-watchlist-form' method='get' name='mw-watchlist-form'>
					<fieldset id='mw-watchlist-options'>
						<legend>".wfMessage('globalwatchlist_options')->escaped()."</legend>
						".wfMessage(($timeFilter >= 24 ? 'below_changes_in_days' : 'below_changes_in'), ($timeFilter >=24 ? $timeFilter / 24 : $timeFilter), $specialPage->getLanguage()->userDate(time(), $specialPage->getUser()), $specialPage->getLanguage()->userTime(time(), $specialPage->getUser()))."<br>
						{$cutOffLinks}<br>
						".implode(' | ', $showHideLinks)."
						<hr>

						<p>
							<label for='site'>".wfMessage('wiki')->escaped().":</label>&nbsp;<select class='siteselector' id='site' name='site'>
								<option value=''".($specialPage->wgRequest->getVal('site') == '' ? " selected='selected'" : null).">".wfMessage('gwl_all_sites')."</option>";
			if (is_array($visibleSites) && count($visibleSites)) {
				//Loop over and output visible sites to choose from.
				foreach ($visibleSites as $siteKey => $name) {
					if (empty($siteKey)) {
						continue;
					}
					$HTML .= "
								<option value='{$siteKey}'".($specialPage->wgRequest->getVal('site') == $siteKey ? " selected='selected'" : null).">{$name}</option>";
				}
			}

			$HTML .= "
							</select>
							<label for='namespace'>".wfMessage('namespace')->escaped()."</label>&nbsp;<select class='namespaceselector' id='namespace' name='namespace'>
								<option value='all'".($specialPage->wgRequest->getVal('namespace') == 'all' ? " selected='selected'" : null).">".wfMessage('gwl_all_ns')."</option>
								<option value='custom'".($specialPage->wgRequest->getVal('namespace') == 'custom' ? " selected='selected'" : null).">".wfMessage('gwl_custom_ns')."</option>";
			$namespaces = $specialPage->getLanguage()->getFormattedNamespaces();
			if (is_array($namespaces) && count($namespaces)) {
				//Loop over and output namespaces.
				foreach ($namespaces as $key => $name) {
					if ($key < 0) {
						continue;
					}
					if ($key === 0) {
						$name = wfMessage('blanknamespace')->escaped();
					}
					$HTML .= "
								<option value='{$key}'".($specialPage->wgRequest->getVal('namespace') == $key && $specialPage->wgRequest->getVal('namespace') != 'all' && $specialPage->wgRequest->getVal('namespace') != 'custom' ? " selected='selected'" : null).">{$name}</option>";
				}
			}

			$HTML .= "
							</select>&nbsp;<input id='nsinvert' name='invert' title='".wfMessage('tooltip-invert')->escaped()."' type='checkbox' value='1'".($specialPage->wgRequest->getInt('invert') == 1 ? " checked='checked'" : null).">&nbsp;"
							."<label for='nsinvert' title='".wfMessage('tooltip-invert')->escaped()."'>".wfMessage('invert')->escaped()."</label>&nbsp;<input id='nsassociated' name='associated' title='".wfMessage('tooltip-namespace_association')->escaped()."' type='checkbox' value='1'".($specialPage->wgRequest->getInt('associated') == 1 ? " checked='checked'" : null).">&nbsp;"
							."<label for='nsassociated' title='".wfMessage('tooltip-namespace_association')->escaped()."'>".wfMessage('namespace_association')->escaped()."</label>&nbsp;<input type='submit' value='".wfMessage('go')->escaped()."'>
						</p>
						<div id='collapse_expand_all'>
							<a href=\"#\" id='expand_all'>".wfMessage('expand_all')->escaped()."</a> / <a href=\"#\" id='collapse_all'>".wfMessage('collapse_all')->escaped()."</a>
						</div>
					</fieldset>
				</form>";

		if (is_array($globalWatchlist) && count($globalWatchlist)) {
			$oldTimestamp = null;
			if ($secondsFilter > 0) {
				$oldTimestamp = time() - $secondsFilter;
			}
			$counter = 0;
			foreach ($globalWatchlist as $siteKey => $articles) {
				if (strlen($siteKey) !== 32 || !is_array($articles)) {
					//This usually indicates a major error.
					continue;
				}
				if (strlen($specialPage->wgRequest->getVal('site')) == 32 && $specialPage->wgRequest->getVal('site') != $siteKey) {
					//They filtered to view only one site.
					continue;
				}

				$_temp = current($articles);
				$site = $_temp['site'];
				$grl = globalRevisionList::newFromSite($siteKey);
				if (!$grl) {
					continue;
				}
				$revisions = $grl->getList();

				$previousDate = null;
				$innerHTML = false;
				foreach ($revisions as $revision) {
					if (!is_object($revision)) {
						continue;
					}
					$foundArticle = $specialPage->searchByKeyValue($articles, ['article'], $revision->getTitle()->getText());
					if ($foundArticle === false) {
						continue;
					} else {
						if (count($foundArticle) == 2) {
							//Both the subject namespace and talk namespace pages were found.  Select the correct one per the revision's namespace.
							if ($revision->getTitle()->getNamespace() == $foundArticle[0]['article']['mNamespace']) {
								$article = $foundArticle[0];
							} else {
								$article = $foundArticle[1];
							}
						} else {
							$article = current($foundArticle);
						}
					}
					$title = $revision->getTitle();

					$filterNamespaces[] = $specialPage->wgRequest->getVal('namespace');
					if ($specialPage->wgRequest->getInt('associated') == 1) {
						if ($specialPage->wgRequest->getVal('namespace') % 2 > 0) {
							$extraNamespace = $specialPage->wgRequest->getVal('namespace') - 1;
						} else {
							$extraNamespace = $specialPage->wgRequest->getVal('namespace') + 1;
						}
						$filterNamespaces[] = $extraNamespace;
					}
					//Check filtering.
					if (
						($revision->isMinor() && $filterOptions['hideMinor']) ||
						($revision->getRawUser() > 0 && $filterOptions['hideLiu']) ||
						($revision->getRawUser() < 1 && $filterOptions['hideAnons']) ||
						($revision->getRawUserText() == $specialPage->getUser()->getName() && $filterOptions['hideOwn']) ||
						(wfTimestamp(TS_UNIX, $revision->getTimestamp()) <= $oldTimestamp && $oldTimestamp !== null) ||
						(is_numeric($specialPage->wgRequest->getVal('namespace')) && !in_array($title->getNamespace(), $filterNamespaces)) ||
						($specialPage->wgRequest->getVal('namespace') == 'custom' && $title->getNamespace() < 100)
					) {
						continue;
					}

					$newDate = $specialPage->getLanguage()->userDate($revision->getTimestamp(), $specialPage->getUser());
					if ($newDate != $previousDate) {
						$previousDate = $specialPage->getLanguage()->userDate($revision->getTimestamp(), $specialPage->getUser());
						if ($ulStarted == true) {
							$innerHTML .= "
							</ul>";
							$ulStarted = false;
						}
						$innerHTML .= "
							<h4>".$previousDate."</h4>";
						$innerHTML .= "
							<ul class='special'>";
						$ulStarted = true;
					}

					//This should include any trailing slashes.
					$siteUrlPrefix = $site['url_prefix'].'/';
					// Sort of hacky way of getting prefixed URL from a title serialized from another wiki.
					// Prefixed text is cached but prefixed URL is not, so we jump through a few hoops to convert one to another.
					// TODO someday write a RemoteTitle superclass to handle some of this stuff automatically?
					$titlePrefixedUrl = str_replace($title->getText(), $title->getDBkey(), $title->getPrefixedText());
					$titlePrefixedUrl = wfUrlencode(str_replace(' ', '_', $titlePrefixedUrl));

					$innerHTML .= "
								<li class='".($counter % 2 == 0 ? "mw-line-even" : "mw-line-odd")."'>
									(<a href='{$siteUrlPrefix}{$titlePrefixedUrl}?diff={$revision->getId()}&amp;oldid={$revision->getParentId()}' tabindex='{$counter}' title='{$title->getPrefixedText()}' target='_blank'>diff</a> | <a href='{$siteUrlPrefix}{$titlePrefixedUrl}?curid={$revision->getPage()}&amp;action=history' title='{$title->getPrefixedText()}' target='_blank'>hist</a>) <span class='mw-changeslist-separator'>. .</span> "
									.($revision->isMinor() ? ChangesList::flag('minor') : null).($revision->getParentId() === 0 ? ChangesList::flag('newpage') : null)
									." <span class='mw-title'><a class='mw-changeslist-title' href='{$siteUrlPrefix}{$titlePrefixedUrl}' title='{$title->getPrefixedText()}' target='_blank'>{$title->getPrefixedText()}</a></span>&lrm;;"
									." <span class='mw-changeslist-date'>".$specialPage->getLanguage()->userTime($revision->getTimestamp(), $specialPage->getUser())."</span> <span class='mw-changeslist-separator'>. .</span>"
									." <span class='mw-plusminus-pos' dir='ltr' title='{$bytes} bytes after change'>(+/-{$bytesChange})</span>&lrm; <span class='mw-changeslist-separator'>. .</span> &lrm;"
									."<a class='mw-userlink' href='{$siteUrlPrefix}User:{$revision->getUserText(Revision::RAW)}' title='User:{$revision->getUserText(Revision::RAW)}' target='_blank'>{$revision->getUserText(Revision::RAW)}</a>"
									."<span class='mw-usertoollinks'>(<a href='{$siteUrlPrefix}User_talk:{$revision->getUserText(Revision::RAW)}' title='User talk:{$revision->getUserText(Revision::RAW)}' target='_blank'>talk</a> | <a href='{$siteUrlPrefix}Special:Contributions/{$revision->getUserText(Revision::RAW)}' title='Special:Contributions/{$revision->getUserText(Revision::RAW)}' target='_blank'>contribs</a>)</span>&lrm; ".($revision->getRawComment() != '' ? "<span class='comment'>({$revision->getRawComment()})</span>" : null)."
								</li>";
					$counter++;
				}
				if ($ulStarted == true) {
					$innerHTML .= "
							</ul>";
					$ulStarted = false;
				}
				$HTML .= "
				<form class='mw-changeslist-site site-{$siteKey}'>
					<fieldset>
						<legend>{$site['wiki_name']}<span class='view_all'>[<a href='{$siteUrlPrefix}Special:Watchlist' target='_blank'>".wfMessage('view_all')->escaped()."</a>]</span><span class='site_expand_collapse'>[ <span title='".wfMessage('collapse_expand')->escaped()."'>-</span> ]</span><span>(".wfMessage('gwl_total_items', $counter)->escaped().")</span></legend>";
				if ($innerHTML !== false) {
					$HTML .= "
						<div class='mw-changeslist'>
							{$innerHTML}
						</div>";
				} else {
					$HTML .= "
						<div class='mw-changeslist-empty'>
							<p>".wfMessage('gwl_noresult')->escaped()."</p>
						</div>";
				}
				$HTML .= "
					</fieldset>
				</form>";
			}
		} else {
			$HTML .= "
			<div class='mw-changeslist'>
				<div class='mw-changeslist-empty'>
					<p>".wfMessage('gwl_noresult')->escaped()."</p>
				</div>
			</div>";
		}

		return $HTML;
	}

	/**
	 * Global Watch List Settings Template
	 *
	 * @access	public
	 * @param	array	Information on Sites
	 * @param	array	Information of visible site keys.
	 * @param	boolean Successful save.
	 * @return	void
	 */
	public function globalWatchlistSettings($sites, $visibleSites, $success) {
		global $wgRequest;

		$HTML .= "
		<h2>".wfMessage('gwl_settings_help')."</h2>";
		if ($success === true) {
			$HTML .= "<div class='successbox gwl'>".wfMessage('gwl_settings_save_success')->escaped()."</div><br/>";
		}
		$HTML .= "
		<input id='inline_site_search' type='text' name='inline_site_search' placeholder='".wfMessage('type_to_search')->escaped()."' value='' class='search_field'/>
		<form id='site_search_form' method='post' action='?do=save'>
			<fieldset>
		";
		if ($error) {
			$HTML .= '<span class="error">'.$error.'</span>';
		}
		$HTML .=  "
				<div id='sites_container'>";
		if (count($sites)) {
			foreach ($sites as $wikiId => $info) {
				$HTML .= "
						<label class='hideable' data-name='".htmlentities(strtolower($info['wiki_name']), ENT_QUOTES)."'><input type='checkbox' name='sites[]' value='{$info['md5_key']}'".(in_array($info['md5_key'], $visibleSites) ? " checked='checked'" : null)."/> {$info['wiki_name']} (".strtoupper($info['wiki_language']).")</label>
						";
			}
		}
		$HTML .= "
				</div>
				<input id='checkAll' type='button' value='".wfMessage('gwl_enable_all')."' />
				<input id='uncheckAll' type='button' value='".wfMessage('gwl_disable_all')."' />
			</fieldset>
			<fieldset class='submit'>
				<input id='wiki_submit' name='wiki_submit' type='submit' value='Save'/>
			</fieldset>
		</form>
		";

		return $HTML;
	}
}
