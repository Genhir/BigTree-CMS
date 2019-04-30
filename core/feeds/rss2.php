<?php
	namespace BigTree;
	
	/**
	 * @global Feed $feed
	 */

	$sort = $feed->Settings["sort"] ? $feed->Settings["sort"] : "id DESC";
	$limit = $feed->Settings["limit"] ? $feed->Settings["limit"] : "15";
	$query = SQL::query("SELECT * FROM `".$feed->Table."` ORDER BY $sort LIMIT $limit");
?><rss version="2.0">
	<channel>
		<title><?php if ($feed->Settings["feed_title"]) { echo $feed->Settings["feed_title"]; } else { echo $feed->Name; } ?></title>
		<link><?php if ($feed->Settings["feed_link"]) { echo $feed->Settings["feed_link"]; } else { ?><?=WWW_ROOT?>feeds/<?=$feed->Route?>/<?php } ?></link>
		<description><?=$feed->Description?></description>
		<language>en-us</language>
		<generator>BigTree CMS (https://www.bigtreecms.org)</generator>
		<?php
			while ($item = $query->fetch()) {
				foreach ($item as $key => $val) {
					$array_val = @json_decode($val,true);
					$item[$key] = Link::decode(is_array($array_val) ? $array_val : $val);
				}
				
				if ($feed->Settings["link_gen"]) {
					$link = $feed->Settings["link_gen"];
					
					foreach ($item as $key => $val) {
						$link = str_replace("{".$key."}",$val,$link);
					}
				} else {
					$link = $item[$feed->Settings["link"]];
				}
				
				$content = $item[$feed->Settings["description"]];
				$limit = $feed->Settings["content_limit"] ? $feed->Settings["content_limit"] : 500;
				$blurb = Text::trimLength($content,$limit);
		?>
		<item>
			<guid><?=WWW_ROOT?>feeds/<?=$feed->Route?>/<?=$item["id"]?>/</guid>
			<title><![CDATA[<?=strip_tags($item[$feed->Settings["title"]])?>]]></title>
			<description><![CDATA[<?=$blurb?><?php if ($blurb != $content) { ?><p><a href="<?=$link?>">Read More</a></p><?php } ?>]]></description>
			<link><?=$link?></link>
			<?php
				if ($feed->Settings["creator"]) {
			?>
			<author><?=$item[$feed->Settings["creator"]]?></author>
			<?php
				}
				
				if ($feed->Settings["date"]) {
			?>
			<pubDate><?=date("D, d M Y H:i:s T",strtotime($item[$feed->Settings["date"]]))?></pubDate>
			<?php
				}
			?>
		</item>
		<?php
			}
		?>
	</channel>
</rss>