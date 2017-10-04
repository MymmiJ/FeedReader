<?php

namespace MymmiJ\FeedReader;

class FeedGetter
{
	public $current_raw_feed;
	public $current_feed_type;

	function __construct() {

	}

	function identify_ambiguous_feed($conType,$feed_sample) {
		if(strpos($conType,"text/xml") !== false) {
			if(strpos($feed_sample,"<rss") !== false) {
				trigger_error("Ambiguous RSS feed format", E_USER_NOTICE);
				return "rss";
			} else if(strpos($feed_sample,"<NewsML>") !== false) {
				trigger_error("Ambiguous NewsML feed format", E_USER_NOTICE);
				return "NewsML";
			} else {
				trigger_error("Highly ambiguous XML format, defaulting to RSS", E_USER_NOTICE);
				return "rss";
			}
		} else if(strpos($conType,"javascript") !== false) {
			trigger_error("Ambiguous JSON feed format or JSONP format", E_USER_NOTICE);
			return "JSON";
		} else if(strpos($conType,"text/html") !== false) {
				trigger_error("HTML is not a supported MymmiJ/FeedReader feed format", E_USER_NOTICE);
				$this->current_feed_type = "html";
		} else {
			trigger_error("No feed type detected - feed is text and cannot be saved",E_USER_WARNING);
			return "txt";
		}
	}

	function getNewFeed($URL) {
		try {
			$this->current_raw_feed = file_get_contents($URL);
			//checking header separately
			$ch = curl_init($URL);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_exec($ch);
			$conType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			//identify correctly declared feeds, else resolve ambiguity
			if(strpos($conType,"application/rss+xml") !== false) {
				$this->current_feed_type = "rss";
			} else if(strpos($conType,"text/vnd.IPTC.NewsML") !== false) {
				$this->current_feed_type = "NewsML";
			} else if(strpos($conType,"application/json") !== false) {
				$this->current_feed_type = "JSON";
			} else {
				$this->current_feed_type = $this->identify_ambiguous_feed($conType,substr($this->current_raw_feed,0,250));
			}
		} catch (\Exception $e) {
			echo $e->getMessage() . PHP_EOL;
		}
	}
}

class Feed
{
	private $raw_feed;
	private $extracted_data;

	function __construct($raw_feed, $data_names = array()) {
		$this->raw_feed = $raw_feed;

		$this->extracted_data = array();
	}

	function get_raw_feed() {
		return $this->raw_feed;
	}

	function get_extracted() {
		return $this->extracted_data;
	}
}

class XMLFeed extends Feed
{
	private $xml;

	function __construct($raw_feed, $data_names = array()) {
		$this->xml = simplexml_load_string($raw_feed);

		$this->extract_data($data_names);
	}

	function extract_data($data_names) {
		$this->extracted_data = array(); //ran out of time to fill out generic case
	}

}

class RSSFeed extends XMLFeed
{

	function __construct($raw_feed, $data_names = array()) {
		$this->xml = simplexml_load_string($raw_feed);

		$this->extract_data($data_names);
	}



	function extract_from_channels($data_names) {
		$channel_name = '';
		$item_name = 'item';
		$item_number = 0;

		if(empty($data_names)) {
			throw new \Exception("Data types to process cannot be empty in " . get_class($this));
		} else {
			foreach($this->xml->channel as $channel) {
				
				$channel_name = $channel->title->__toString();

				$this->extracted_data[$channel_name] = array();

				foreach($channel->item as $item) {
					$item_name = 'item' . (string)$item_number;
					$item_number++;

					$this->extracted_data[$channel_name][$item_name] = array();

					foreach($data_names as $data_name) {
						$element = $item->xpath($data_name);

						if(is_array($element)) {
							foreach($element as $e) {
								array_push($this->extracted_data[$channel_name][$item_name], $e->__toString());
							}
						} else if($element===NULL) {
							array_push($this->extracted_data[$channel_name][$item_name],"");
						}else {
							array_push($this->extracted_data[$channel_name][$item_name],$element->__toString());
						}

					}
					
				}
				
			}
		}
	}

	function extract_data($data_names) {
		$this->extracted_data = array();
		
		$this->extract_from_channels($data_names);
	}
}

class NewsMLFeed extends XMLFeed
{
	function __construct($raw_feed, $data_names = array()) {
		$this->xml = simplexml_load_string($raw_feed);

		$this->extract_data($data_names);

		var_dump($this->extracted_data);
	}



	function extract_from_items($data_names) {
		$item_name = 'NewsItem';
		$item_number = 0;

		if(empty($data_names)) {
			throw new Exception("Data types to process cannot be empty in " . get_class($this));
		} else {
			$item_name = 'NewsItem' . (string)$item_number;
			$item_number++;

			$this->extracted_data[$item_name] = array();

			foreach($data_names as $data_name) {
				$element = $this->xml->xpath('//' . $data_name);
				foreach($element as $elem) {
					$href = '';						

					if(isset($elem['Href'])) $href = (string) $elem['Href'];

					if(is_array($elem)) {
						foreach($elem as $e) {
							if(isset($elem['Href'])) $href = (string) $elem['Href'];

							array_push($this->extracted_data[$item_name], $e->__toString() . $href);
						}
					} else if($element===NULL) {
						array_push($this->extracted_data[$item_name],"");
					}else {
						array_push($this->extracted_data[$item_name],$elem->__toString() . $href);

					}

				}
			}
					
		}	
				
			
	}

	function extract_data($data_names) {
		$this->extracted_data = array();
		
		$this->extract_from_items($data_names);
	}
}

class JSONFeed extends Feed
{
	private $json;

	function __construct($raw_feed, $data_names = array()) {
		$this->json = json_decode($raw_feed);

		$this->extract_data($data_names);

		var_dump($this->extracted_data);
	}

	function extract_from_items($data_names) {
		$item_name = 'JSONitem';
		$item_number = 0;


		$items = $this->json->items;

		foreach($items as $item) {
			$item_name = 'JSONitem' . $item_number;
			$item_number++;

			$this->extracted_data[$item_name] = array();

			foreach($data_names as $data_name) {
				
				$element = $item->$data_name;

				if($element===NULL) {
					$this->extracted_data[$item_name][$data_name] = "";
				} else {
					$this->extracted_data[$item_name][$data_name] = $element;
				}
			}
		}
	}

	function extract_data($data_names) {
		$this->extracted_data = array();

		$this->extract_from_items($data_names);
	}}

?>