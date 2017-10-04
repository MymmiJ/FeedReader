<?php

namespace MymmiJ\FeedReader;

require_once 'Feed.php';

class FeedReader
{
	public $feed;
	private $getFeed;

	function __construct($URL) {
		$this->getFeed = new FeedGetter();

		$this->read_feed($URL);
	}

	function read_feed($URL) {
		$this->getFeed->getNewFeed($URL);

		switch($this->getFeed->current_feed_type) {
			case "rss":
				$this->feed = new RSSFeed($this->getFeed->current_raw_feed,array('title','description','pubDate','image'));
				break;
			case "NewsML":
				$this->feed = new NewsMLFeed($this->getFeed->current_raw_feed,array('HeadLine','SlugLine','DateId','ContentItem[@Href][MediaType[@FormalName="CTD_IMAGE"]]'));
				break;
			case "JSON":
				$this->feed = new JSONFeed($this->getFeed->current_raw_feed,array('title','summary','date_published','image'));
				break;
			case "xml":
				$this->feed = new XMLFeed($this->getFeed->current_raw_feed);
				break;
			default:
				$this->feed = new Feed($this->getFeed->current_raw_feed);
				break;
		}
	}

	function save_data_from_feed($file) {
		if(file_exists($file)) {
			throw new \Exception("Delete file " . $file . " before running " . get_class($this));
		} else {
			$serialized_data = serialize($this->feed->extracted_data);

			file_put_contents($file,$serialized_data);
		}
	}

	//TODO: write loader
}

$feed_reader = new FeedReader("http://feeds.bbci.co.uk/news/rss.xml");

$feed_reader->save_data_from_feed("bbc-news.txt");

//$getFeed = new FeedGetter();

//$getFeed->getNewFeed("http://www.techradar.com/rss"); //also "http://feeds.bbci.co.uk/news/rss.xml"

//$feed = $getFeed->current_raw_feed;

//$rssFeed = new RSSFeed($feed,array('title','description','pubDate','image'));

//$getFeed->getNewFeed("http://feeds.skynews.com/feeds/newsml/home");

//$feed = $getFeed->current_raw_feed;

//$newsMLFeed = new NewsMLFeed($feed,array('HeadLine','SlugLine','DateId','ContentItem[@Href][MediaType[@FormalName="CTD_IMAGE"]]'));

//$getFeed->getNewFeed("https://jsonfeed.org/feed.json");

//$feed = $getFeed->current_raw_feed;

//$jsonFeed = new JSONFeed($feed,array('title','summary','date_published','image'));

?>