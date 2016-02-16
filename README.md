# GlobalWatchlist
A watch list that aggregates watch lists from all wikis to any wiki on a single special page.

###Please Note: This code is alpha quality and contains code that will not work outside the Hydra Wiki Platform.
These steps need to be done to make it overall compatible with non-Hydra Wiki platforms.
* The MASTER_WIKI define needs to be improved to not be dependent on Extension:DynamicSettings.
* The $wgSiteKey is a wiki identifier much like "enwiki" in farm setups or the database identifier.  Set $wgSiteKey to a valid site key in LocalSettings.php for each respective site.  On the Hydra Wiki Platform this automatically configured.
 * To invoke database schema updates on the master wiki, the one that will contain the single source of database tables, define $wgSiteKey as master.

###Requirements
* PHP 5.4+
* [Extension:RedisCache](https://github.com/HydraWiki/RedisCache)
 * PHP Redis extension
* [Extension:SyncService](https://github.com/HydraWiki/SyncService)

###This code is slow on large combined watchlists.
Currently the watchlists and revision objects are serialized into Redis.  This means users with large combined watchlists, several hundred pages, will quickly hit the PHP memory limit.  The current suggested fixes are:
* Do not serialize the objects and instead create more simple representations of the data.
* Use a centralized notification system like Echo to indicate there are new unread items in a watchlist.
 * Combined with AJAX loading of items from the remote wikis.